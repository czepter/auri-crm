<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\ConsumeGalleryEmailVerification;
use App\Gallery\Actions\RequestGalleryEmailVerification;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryAccessSession;
use App\Gallery\Support\GalleryVisitor;
use App\Gallery\Support\GallerySecretGate;
use App\Http\Controllers\Controller;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Jobs\BuildZip;
use App\Media\Models\Asset;
use App\Media\Models\AssetDerivative;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The download screen — G-03 (#231), mockup galerie-download.html — behind
 * `gallery.gate:view`, since a guest reaches it from inside an already
 * unlocked gallery, never before it. show() decides which of the two gates
 * still needs asking the same way GalleryPasswordController::show() decides
 * unlocked-or-not: there is nowhere else to redirect a failing request to
 * but back to itself, so this owns the branching rather than being wrapped
 * by the gates it renders.
 *
 * Once both are satisfied the screen is no longer a placeholder saying
 * "Alles bereit". Two things about it come out of the model rather than out
 * of taste:
 *
 * There are parts, not a file. BuildGalleryZip plans volumes through
 * ZipVolumePlanner and ZipBuildPart is the delivered unit, so one button
 * would be lying about 33 originals at 4.2 GB. The parts stand as a list,
 * each with its own state.
 *
 * What gets downloaded is a list. All, or one of the client's own selections
 * (#225), at Original or "Zum Teilen" — with a size on every line, so
 * nobody starts a four-gigabyte transfer to find out how big it was.
 */
class GalleryDownloadController extends Controller
{
    /** How long a prepared archive stands, and how long each part's link is good for. */
    public const PARTS_LIVE_HOURS = 24;

    public function __construct(private readonly GalleryVisitor $visitor) {}

    public function show(Request $request, Gallery $gallery, GalleryAccessSession $claims): Response
    {
        $step = $this->step($gallery, $claims);
        $version = $gallery->currentVersion;

        return Inertia::render('Gallery/Download', [
            'gallery' => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'count' => $version === null ? 0 : count($version->assets),
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
            ],
            'step' => $step,
            // The gates as cleared marks, so the screen says what was asked
            // and answered rather than forgetting it the moment it passes.
            'gates' => $step === 'ready' ? $this->clearedGates($gallery, $claims) : null,
            'selections' => $step === 'ready' && $version !== null
                ? $this->selections($gallery, $version)
                : [],
            'builds' => $step === 'ready' && $version !== null
                ? $this->builds($request, $gallery, $version)
                : [],
        ]);
    }

    /**
     * "Bilder vorbereiten". Reuses a Ready build rather than making a second
     * archive of the same bytes; a Failed one is rebuilt, and anything older
     * than the window below is rebuilt too, because its parts are stale.
     */
    public function prepare(Request $request, Gallery $gallery, GalleryAccessSession $claims): RedirectResponse
    {
        abort_unless($this->step($gallery, $claims) === 'ready', 404);

        $version = $gallery->currentVersion;
        abort_if($version === null, 404);

        $validated = $request->validate([
            'list' => ['nullable', 'string'],
            'size' => ['required', Rule::enum(ZipBuildSize::class)],
        ]);

        $list = ($validated['list'] ?? '') === ''
            ? null
            : $this->ownList($gallery, $validated['list']);

        $size = ZipBuildSize::from($validated['size']);

        $build = ZipBuild::query()->firstOrNew([
            'gallery_version_id' => $version->id,
            'scope' => $list === null ? ZipBuildScope::All : ZipBuildScope::ListSelection,
            'size' => $size,
            'gallery_list_id' => $list?->id,
        ]);

        if ($build->exists && $build->status === ZipBuildStatus::Ready && $this->isFresh($build)) {
            return back();
        }

        $build->fill(['status' => ZipBuildStatus::Building, 'error' => null])->save();
        $build->parts()->delete();

        BuildZip::dispatch($build);

        return back();
    }

    /**
     * $gallery arrives as the raw slug-or-public_id rather than an implicit-bound
     * model, same reasoning as GalleryPasswordController::store: a 404 from
     * route-model binding would tell a PIN-guesser "that id doesn't exist"
     * before this method — or EnsureGalleryGate — ever ran. EnsureGalleryGate
     * lets a non-Gallery route value straight through for the same reason;
     * see its own note.
     */
    public function storePin(Request $request, string $gallery, GalleryAccessSession $claims, GallerySecretGate $gate): RedirectResponse
    {
        $model = Gallery::findByPublicIdOrSlug($gallery);
        $requiresPin = $model === null || $model->requiresDownloadPin();

        if ($requiresPin) {
            $validated = $request->validate(['pin' => ['required', 'string']]);

            $granted = $gate->attempt($request->ip(), $gallery, $model === null ? null : $model->checkDownloadPin(...), $validated['pin']);

            if (! $granted || $model === null) {
                return back()->withErrors(['pin' => __('gallery.pin_wrong')]);
            }
        }

        $claims->grant($model, GalleryAccessSession::DOWNLOAD, $model->download_pin_version);

        return redirect("/galerien/{$model->public_id}/download");
    }

    /**
     * Rate limited the same two ways as the password/PIN checks (G-07),
     * even though there is no wrong-vs-right secret here to guess — see
     * RequestGalleryEmailVerification's own docblock for why this always
     * sends. Without a per-gallery cap, one gallery link would let anyone
     * spam an unlimited number of inboxes through the studio's own mail
     * sender; without a per-IP cap, one visitor could do the same across
     * every gallery it can reach.
     */
    public function requestEmail(Request $request, Gallery $gallery, RequestGalleryEmailVerification $issue): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $ipKey = 'gallery-email-ip:'.($request->ip() ?? 'unknown');
        $galleryKey = 'gallery-email-gallery:'.$gallery->public_id;

        if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($galleryKey, 30)) {
            return back()->with('status', __('gallery.email_link_sent'));
        }

        RateLimiter::hit($ipKey, 300);
        RateLimiter::hit($galleryKey, 300);

        $issue($gallery, $validated['email']);

        return back()->with('status', __('gallery.email_link_sent'));
    }

    public function verifyEmail(
        Gallery $gallery,
        string $token,
        ConsumeGalleryEmailVerification $consume,
        GalleryAccessSession $claims,
    ): RedirectResponse {
        $email = $consume($gallery, $token);

        if ($email === null) {
            return redirect("/galerien/{$gallery->public_id}/download")->with('status', __('gallery.email_link_dead'));
        }

        $claims->grant($gallery, GalleryAccessSession::EMAIL, $gallery->email_verification_version, ['email' => $email]);

        return redirect("/galerien/{$gallery->public_id}/download");
    }

    /**
     * What a visitor may ask for: everything, plus the signed-in client's own
     * selections (#225). A guest sees only "Alle" — a named list belongs to
     * the client, and G-02 draws the same line in the grid's filter bar.
     *
     * Sizes come off Asset::byte_size and the widest WebP derivative's own
     * bytes, the same two numbers ZipVolumePlanner plans volumes with, so
     * the figure on the line is the figure that gets transferred.
     */
    private function selections(Gallery $gallery, GalleryVersion $version): array
    {
        $keys = collect($version->assets)
            ->map(fn (array|string $asset) => is_array($asset) ? ($asset['key'] ?? null) : $asset)
            ->filter();

        // name null means "everything" and, on a list, "the heart" — the
        // screen names both. Copy stays in de.json rather than being split
        // between there and a PHP lang file nobody would think to look in.
        $rows = [[
            'id' => '',
            'name' => null,
            'count' => $keys->count(),
            'sizes' => $this->sizesFor($gallery, $keys->all()),
        ]];

        foreach ($this->visitor->lists($gallery) as $list) {
            $onList = $list->items->pluck('asset_key')->intersect($keys)->values();

            if ($onList->isEmpty()) {
                continue;
            }

            $rows[] = [
                'id' => $list->public_id,
                'name' => $list->name,
                'count' => $onList->count(),
                'sizes' => $this->sizesFor($gallery, $onList->all()),
            ];
        }

        return $rows;
    }

    /**
     * Bytes per size option for one set of keys.
     *
     * ponytail: two queries per selection row, so a client with four lists
     * pays eight. Fine at a gallery's size; group them if a venue with forty
     * lists ever appears.
     *
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function sizesFor(Gallery $gallery, array $keys): array
    {
        $assets = Asset::query()
            ->where('gallery_id', $gallery->id)
            ->whereIn('key', $keys)
            ->where('status', AssetStatus::Ready)
            ->get();

        $web = AssetDerivative::query()
            ->whereIn('asset_id', $assets->pluck('id'))
            ->where('format', 'webp')
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($derivatives) => (int) $derivatives->sortByDesc('width')->first()?->bytes);

        return [
            ZipBuildSize::Original->value => (int) $assets->sum('byte_size'),
            ZipBuildSize::Web->value => (int) $web->sum(),
        ];
    }

    /**
     * Every archive this visitor has already asked for on this version, with
     * its parts. A part carries a signed, expiring link bound to the guest
     * id it was minted for — see the delivery route in routes/portal.php.
     */
    private function builds(Request $request, Gallery $gallery, GalleryVersion $version): array
    {
        // Every archive this visitor may see. A guest cleared the view gate,
        // so the photographs are theirs to look at — the couple's curation is
        // not. A list-scoped build carries their words ("Für die Eltern") and
        // their selection, so it belongs to whoever owns the list and nobody
        // else. Scoped in the query rather than filtered after, so a build
        // nobody may see never reaches a signed URL.
        $ownLists = $this->visitor->lists($gallery)->pluck('id');

        return ZipBuild::query()
            ->where('gallery_version_id', $version->id)
            ->where(fn ($query) => $query
                ->whereNull('gallery_list_id')
                ->orWhereIn('gallery_list_id', $ownLists))
            ->with(['parts', 'galleryList'])
            ->get()
            ->map(fn (ZipBuild $build) => [
                'id' => $build->public_id,
                'list' => $build->galleryList?->public_id ?? '',
                'listName' => $build->galleryList?->name,
                'size' => $build->size->value,
                'status' => $build->status->value,
                'partCount' => $build->parts->count(),
                'parts' => $build->status === ZipBuildStatus::Ready
                    ? $build->parts->sortBy('part_number')->map(fn (ZipBuildPart $part) => [
                        'number' => $part->part_number,
                        'bytes' => (int) $part->byte_size,
                        'url' => $this->partUrl($request, $gallery, $part),
                    ])->values()->all()
                    : [],
            ])
            ->values()
            ->all();
    }

    private function partUrl(Request $request, Gallery $gallery, ZipBuildPart $part): string
    {
        return URL::temporarySignedRoute('gallery.download.part', now()->addHours(self::PARTS_LIVE_HOURS), [
            'gallery' => $gallery->public_id,
            'zipBuildPart' => $part->public_id,
            'guestId' => $request->session()->getId(),
        ]);
    }

    /**
     * Which gates this visitor cleared. Keys rather than sentences, except
     * the verified address, which is the visitor's own text and not
     * something to translate.
     *
     * @return array<int, array{key: string, value: string|null}>
     */
    private function clearedGates(Gallery $gallery, GalleryAccessSession $claims): array
    {
        return array_values(array_filter([
            $gallery->requiresViewPassword() ? ['key' => 'gateView', 'value' => null] : null,
            $gallery->requiresDownloadPin() ? ['key' => 'gateDownload', 'value' => null] : null,
            $gallery->requiresVerifiedEmail()
                ? ['key' => 'gateEmail', 'value' => $claims->verifiedEmail($gallery)]
                : null,
        ]));
    }

    private function ownList(Gallery $gallery, string $list): GalleryList
    {
        $found = $this->visitor->lists($gallery)->firstWhere('public_id', $list);

        abort_if($found === null, 404);

        return $found;
    }

    /** ponytail: no reaper for stale volumes — see BuildGalleryZip's own note. A stale build is simply rebuilt. */
    private function isFresh(ZipBuild $build): bool
    {
        return $build->updated_at->gt(now()->subHours(self::PARTS_LIVE_HOURS));
    }

    private function step(Gallery $gallery, GalleryAccessSession $claims): string
    {
        $hasPin = ! $gallery->requiresDownloadPin()
            || $claims->has($gallery, GalleryAccessSession::DOWNLOAD, $gallery->download_pin_version);

        $hasEmail = ! $gallery->requiresVerifiedEmail()
            || $claims->has($gallery, GalleryAccessSession::EMAIL, $gallery->email_verification_version);

        return match (true) {
            ! $hasPin => 'pin',
            ! $hasEmail => 'email',
            default => 'ready',
        };
    }
}
