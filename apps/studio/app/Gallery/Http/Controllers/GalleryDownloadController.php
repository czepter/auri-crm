<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\ConsumeGalleryEmailVerification;
use App\Gallery\Actions\RequestGalleryEmailVerification;
use App\Gallery\Actions\StartZipBuild;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryAccessSession;
use App\Gallery\Support\GallerySecretGate;
use App\Gallery\Support\GalleryVisitor;
use App\Gallery\Support\ZipSizes;
use App\Http\Controllers\Controller;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
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
                'count' => $version === null ? 0 : count($version->assetsFor($this->visitor->isClient($gallery))),
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
     * The "Herunterladen" mail's own entry point (IssueGalleryDownloadLink).
     * Only checks the token; the PIN/email gates on this route group apply
     * exactly as they do to a guest who found show() by ordinary navigation.
     */
    public function openFromMail(Gallery $gallery, string $token): RedirectResponse
    {
        abort_unless(
            GalleryDownloadLink::forToken($token)?->gallery_id === $gallery->id,
            404,
        );

        return redirect()->route('gallery.download.show', ['gallery' => $gallery->public_id]);
    }

    /** "Bilder vorbereiten". */
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

        app(StartZipBuild::class)(
            $version,
            ZipBuildSize::from($validated['size']),
            $list,
            $claims->verifiedEmail($gallery) ?? $this->clientEmail($gallery),
            self::PARTS_LIVE_HOURS,
            ! $this->visitor->isClient($gallery),
        );

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
        $model = Gallery::findByReference($gallery);
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
    public function requestEmail(
        Request $request,
        Gallery $gallery,
        RequestGalleryEmailVerification $issue,
        GalleryAccessSession $claims,
    ): RedirectResponse {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Verlangt das Studio keinen Nachweis, ist die Adresse kein Tor,
        // sondern nur das Ziel für die fertige Mail. Einen Code zu schicken,
        // den das Studio abgewählt hat, wäre Reibung ohne Auftrag.
        if (! $gallery->requiresVerifiedEmail()) {
            GalleryGuest::forGallery($gallery, $validated['email']);
            $claims->grant(
                $gallery,
                GalleryAccessSession::EMAIL,
                $gallery->email_verification_version,
                ['email' => $validated['email']],
            );

            return back();
        }

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
     */
    private function selections(Gallery $gallery, GalleryVersion $version): array
    {
        $keys = collect($version->assetsFor($this->visitor->isClient($gallery)))
            ->map(fn (array|string $asset) => is_array($asset) ? ($asset['key'] ?? null) : $asset)
            ->filter();

        // name null means "everything" and, on a list, "the heart" — the
        // screen names both. Copy stays in de.json rather than being split
        // between there and a PHP lang file nobody would think to look in.
        $rows = [[
            'id' => '',
            'name' => null,
            'count' => $keys->count(),
            'sizes' => ZipSizes::for($gallery, $keys->all()),
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
                'sizes' => ZipSizes::for($gallery, $onList->all()),
            ];
        }

        return $rows;
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
            ->where('guests', ! $this->visitor->isClient($gallery))
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

    /**
     * Wer hier herunterlädt. Die Kennung des bestätigten Gastes statt der
     * Sitzungs-ID: download_events.requester_identifier meint damit zum
     * ersten Mal einen Menschen — dieselbe Kennung, unter der auch seine
     * Herzen und Kommentare liegen —, und ein fertiges ZIP weiß, wem es
     * Bescheid zu sagen hat.
     *
     * Ohne bestätigte Adresse bleibt es die Sitzung: eine Galerie ohne
     * Adressprüfung lässt herunterladen, ohne je nach einer zu fragen.
     */
    private function partUrl(Request $request, Gallery $gallery, ZipBuildPart $part): string
    {
        return URL::temporarySignedRoute('gallery.download.part', now()->addHours(self::PARTS_LIVE_HOURS), [
            'gallery' => $gallery->public_id,
            'zipBuildPart' => $part->public_id,
            'guestId' => $this->requester($request, $gallery),
        ]);
    }

    /** Die Adresse eines angemeldeten Kunden dieser Galerie, sonst null. */
    private function clientEmail(Gallery $gallery): ?string
    {
        $contact = auth('portal')->user();

        return $contact !== null
            && $gallery->case_id !== null
            && $contact->visibleProjects()->whereKey($gallery->case_id)->exists()
                ? $contact->email
                : null;
    }

    private function requester(Request $request, Gallery $gallery): string
    {
        $email = app(GalleryAccessSession::class)->verifiedEmail($gallery) ?? $this->clientEmail($gallery);

        return $email === null
            ? $request->session()->getId()
            : GalleryGuest::identifierFor($gallery, $email);
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

    /**
     * Erst die PIN, dann die Adresse.
     *
     * Die PIN entscheidet, ob überhaupt jemand herunterladen darf — von
     * jemandem eine Adresse einzusammeln, den man gleich abweist, ist
     * Erhebung ohne Zweck.
     *
     * Eine Adresse verlangt der Download seither immer: ein Archiv über
     * 14 GB wird im Hintergrund gepackt, und ohne Adresse hat das fertige
     * niemanden, dem es Bescheid sagt (GalleryZipReady). Ob sie **bestätigt**
     * sein muss, entscheidet weiterhin das Studio — siehe requestEmail().
     */
    private function step(Gallery $gallery, GalleryAccessSession $claims): string
    {
        $hasPin = ! $gallery->requiresDownloadPin()
            || $claims->has($gallery, GalleryAccessSession::DOWNLOAD, $gallery->download_pin_version);

        // Ein angemeldeter Kunde hat seine Adresse im Konto — ihn danach zu
        // fragen wäre dieselbe Frage zum zweiten Mal. Verlangt das Studio
        // einen Nachweis, gilt das nicht: dann ist die Adresse ein Tor und
        // kein Zustellziel.
        $hasEmail = $claims->has($gallery, GalleryAccessSession::EMAIL, $gallery->email_verification_version)
            || (! $gallery->requiresVerifiedEmail() && $this->clientEmail($gallery) !== null);

        return match (true) {
            ! $hasPin => 'pin',
            ! $hasEmail => 'email',
            default => 'ready',
        };
    }
}
