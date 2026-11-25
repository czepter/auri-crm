<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Models\ProjectType;
use App\Documents\Data\MailTemplateData;
use App\Documents\Support\DocumentMergeData;
use App\Gallery\Actions\CopyGalleryAssets;
use App\Gallery\Actions\DeleteGallery;
use App\Gallery\Actions\DuplicateGallery;
use App\Gallery\Actions\UpdateGallery;
use App\Gallery\Data\GalleryNavData;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGridPref;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryPreset;
use App\Gallery\Models\GallerySet;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Models\GalleryView;
use App\Gallery\Support\DraftDeviation;
use App\Gallery\Support\GalleryActivity;
use App\Gallery\Support\GalleryDesign;
use App\Gallery\Support\GalleryDownloadGate;
use App\Gallery\Support\GalleryPermissions;
use App\Gallery\Support\GallerySlug;
use App\Http\Controllers\Controller;
use App\Media\Data\FailedAssetData;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Enums\ZipBuildSize;
use App\Media\Models\Asset;
use App\Media\Models\DownloadEvent;
use App\Media\Models\MultipartUpload;
use App\Media\Models\ZipBuild;
use App\Models\StudioSettings;
use App\Payments\Models\Invoice;
use App\Support\LiveUpdate;
use App\Support\RecordVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * C-19 (#255): the gallery as something one manages, not a data sheet with
 * switches.
 *
 * In practice a gallery is worked on in five separate motions — order the
 * pictures, settle how it looks, regulate the download, set the permissions,
 * look up who took what — so those are five tabs and not five cards stacked
 * on one surface. The head sits above them because link, password, code and
 * the figures hold for every tab; they would otherwise travel from tab to tab
 * or, worse, differ between them.
 *
 * Link, view password and download code stand in clear and are settable here,
 * which is what C-17 (#253) stored them recoverably for. Both are handed to
 * guests anyway; a studio that cannot look one up sets a new one on every
 * enquiry and locks out everybody holding the old.
 *
 * The versions sit with the pictures rather than with the permissions: a
 * version is a published selection, so it is a statement about pictures.
 */
class GalleryRecordController extends Controller
{
    /** Die Abschnitte der Einstellungen. Kein Modell, nur fünf Wörter. */
    private const SECTIONS = ['zugang', 'download', 'darstellung'];

    public function __construct(private readonly DocumentMergeData $mergeData) {}

    /**
     * Drei Eingänge, eine Render-Methode. Ein einziger Eingang mit
     * `?GallerySet $set = null` ginge nicht: Laravels implizite Bindung
     * überspringt einen Parameter mit Vorgabewert, der Pfadwert käme als
     * Zeichenkette an — und weil nie gebunden wird, feuerte auch missing()
     * nicht.
     */
    public function show(Gallery $gallery, string $bereich = 'bilder'): Response
    {
        return $this->render($gallery, $bereich, null, null);
    }

    public function set(Gallery $gallery, GallerySet $set): Response
    {
        return $this->render($gallery, 'bilder', $set, null);
    }

    public function section(Gallery $gallery, string $abschnitt): Response|RedirectResponse
    {
        // Ein Lesezeichen auf einen umbenannten Abschnitt landet im Bereich,
        // nicht auf einer Fehlerseite — der Bereich existiert ja weiter.
        if (! in_array($abschnitt, self::SECTIONS, true)) {
            return redirect()->route('galleries.show.section', [$gallery, 'zugang']);
        }

        return $this->render($gallery, 'einstellungen', null, $abschnitt);
    }

    /** Der Text der Mail-Vorlage der Galerie-Vorlage; eine mit unbekanntem Feld wird nicht vorbelegt. */
    private function mailMessage(Gallery $gallery): ?string
    {
        $template = $gallery->preset?->mailTemplate();

        if ($template === null) {
            return null;
        }

        $mail = MailTemplateData::fromModel($template, $this->mergeData->forCase($gallery->case));

        return $mail->unknownFields === [] ? $mail->body : null;
    }

    private function render(Gallery $gallery, string $bereich, ?GallerySet $set, ?string $abschnitt): Response
    {
        $this->authorize('manageMedia', $gallery);

        RecordVisit::record('galerie', $gallery->public_id, $gallery->title ?? $gallery->case?->name ?? '');

        $version = $gallery->currentVersion;
        // Nur die Zähler: die Spalte braucht sie in jedem Bereich, die Zeilen
        // selbst nur dort, wo Bilder zu sehen sind.
        $sets = $gallery->sets()
            ->withCount(['assets' => fn ($query) => $query->inDraft()])
            ->get();

        // Same order Gallery::draftAssets() produces — sets by position,
        // each set by its own rule — so Alle in the pictures pane reads the
        // same order a guest would eventually get.
        $assets = function () use ($sets) {
            $sets->load(['assets' => fn ($query) => $query->inDraft()]);

            return $sets->flatMap(fn (GallerySet $set) => $set->assets
                ->sortBy([
                    [fn (Asset $a, Asset $b) => ($a->{$set->sort_key} === null) <=> ($b->{$set->sort_key} === null), 'asc'],
                    [$set->sort_key, $set->sort_dir],
                    ['filename', 'asc'],
                ])
                ->each(fn (Asset $asset) => $asset->setRelation('set', $set)));
        };
        $densities = GalleryGridPref::query()
            ->where('user_id', auth('staff')->id())
            ->whereIn('gallery_set_id', $sets->pluck('id'))
            ->pluck('density', 'gallery_set_id');

        // Bytes the studio already sent that G-03 has not turned into an
        // Asset yet — still transferring (in_progress), or landed and
        // waiting on the media.ingest queue (completed, processed_at null).
        // A Completed row with processed_at set already resolved into an
        // Asset (Ready or Failed) and is excluded — this is only the gap
        // between "sent" and "ingest concluded".
        $pendingUploads = fn () => MultipartUpload::query()
            ->where('gallery_id', $gallery->id)
            ->where('status', '!=', MultipartUploadStatus::Aborted)
            ->whereNull('processed_at')
            ->with('set')
            ->oldest()
            ->get();

        $primary = $gallery->case?->primaryContact();

        return Inertia::render('Galleries/Record', [
            // Bereich, Set und Abschnitt kommen aus dem Pfad, nicht aus einem
            // ref() — sonst wirft jedes Neuladen den Menschen zurück auf
            // Bilder/Alle.
            'area' => $bereich,
            // Bei genau einem Set ist die nackte URL dieses Set. „Alle"
            // gibt es dann nicht, und ohne aktives Set wäre die Dropzone
            // ohne Ziel — das war R22.
            'activeSetId' => $set?->public_id ?? ($sets->count() === 1 ? $sets->first()->public_id : null),
            'settingsSection' => $abschnitt ?? 'zugang',
            'nav' => LiveUpdate::live(GalleryNavData::build($gallery->loadMissing('coverAsset'), $sets, $version)),
            'gallery' => LiveUpdate::live([
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'url' => $gallery->publicUrl(),
                'eventDate' => $gallery->occursOn()?->format('Y-m-d'),
                // C-17 (#253): in clear, because both values are shared with
                // guests anyway and a studio that cannot read one back is
                // forced to rotate it on every enquiry.
                'viewPassword' => $gallery->view_password,
                'downloadPin' => $gallery->download_pin,
                'requiresVerifiedEmail' => $gallery->requiresVerifiedEmail(),
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
                'design' => $gallery->design,
                'coverFocal' => $gallery->coverFor($gallery->coverAsset?->key)['focal'],
                // Drives the publish button's label (Veröffentlichen ·
                // Neu veröffentlichen · Wieder freigeben) — computed fresh
                // rather than a stored flag a future action can forget to
                // update (spec decision 23).
                'deviates' => DraftDeviation::of($gallery),
                'withdrawn' => $gallery->isWithdrawn(),
                'prerelease' => $gallery->isInPrerelease(),
                // Minor fix round 1: Pre-release is unreachable for an external
                // gallery (GalleryPrereleaseController::store() 422s it), but
                // Record.vue had no way to know that and offered the control
                // anyway. isExternal lets the menu item hide itself instead.
                'isExternal' => $gallery->is_external,
                'externalUrl' => $gallery->external_url,
                'externalPassword' => $gallery->external_password,
                'info' => $gallery->info,
                'canPublish' => auth('staff')->user()->can('publish', $gallery),
                'canDelete' => auth('staff')->user()->can('delete', $gallery),
                'copy' => $gallery->copyProgress(),
                // Der Senden-Dialog zeigt den E-Mail-Reiter nur, wenn es einen Empfänger gibt.
                'canMail' => filled($primary?->email),
                'recipient' => $primary ? ['name' => $primary->name, 'email' => $primary->email] : null,
                'mailMessage' => $this->mailMessage($gallery),
                'studioName' => StudioSettings::current()->display_name,
                'viewMode' => $gallery->view_mode,
                'watermarkText' => $gallery->watermark_text,
                'downloadRule' => $gallery->download_rule,
                'downloadInvoiceId' => $gallery->downloadInvoice?->public_id,
                'downloadSizes' => $gallery->download_sizes,
                'expiryReminder' => $gallery->expiry_reminder,
                'followsPlan' => GalleryDownloadGate::followsPlan($gallery),
                'invoices' => $gallery->case_id === null ? [] : Invoice::query()
                    ->where('case_id', $gallery->case_id)
                    ->with('installments')
                    ->orderBy('issued_at')
                    ->get()
                    ->map(fn (Invoice $invoice) => [
                        'id' => $invoice->public_id,
                        'number' => $invoice->number,
                        'status' => $invoice->status(),
                    ])
                    ->all(),
                'offers' => [
                    'downloads' => $gallery->downloads_enabled,
                    'lists' => $gallery->lists_enabled,
                    'comments' => $gallery->comments_enabled,
                ],
                'permissions' => GalleryPermissions::normalize($gallery->permissions),
                'introEnabled' => $gallery->intro_enabled,
                'listedOnHome' => $gallery->listed_on_home,
                'homePinned' => $gallery->home_pinned,
                'homeBlocker' => $gallery->homeBlocker(),
                'homeMode' => StudioSettings::current()->gallery_home_mode,
                'categoryId' => $gallery->projectType?->public_id,
                'categories' => ProjectType::query()
                    ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $gallery->project_type_id))
                    ->orderBy('sort_order')->orderBy('id')->get()
                    ->map(fn (ProjectType $type) => ['id' => $type->public_id, 'name' => $type->name])->all(),
            ]),
            // The other galleries of the same booking, as a tab row: they are
            // the ones one flips between.
            'siblings' => $this->inArea(['einstellungen'], $bereich, fn () => $gallery->case_id === null ? [] : Gallery::query()
                ->where('case_id', $gallery->case_id)
                ->withCount(['assets as asset_count' => fn ($query) => $query->inDraft()])
                ->get()
                ->map(fn (Gallery $other) => [
                    'id' => $other->public_id,
                    'title' => $other->title,
                    'assetCount' => $other->asset_count,
                    'open' => $other->id === $gallery->id,
                ])->all()),
            // The More menu's "Vorlage anwenden" — Create.vue's own copy of
            // this list, seen the other direction: birth reads a preset
            // once into a new gallery's form, this writes one into a
            // gallery that already exists (GalleryPresetController::
            // applyToGallery).
            'presets' => GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get()),
            'figures' => LiveUpdate::live([
                'published' => $version?->version,
                'assets' => $sets->sum('assets_count'),
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
                'marked' => $version === null ? 0 : AssetFavorite::query()->where('gallery_version_id', $version->id)->count(),
                'downloaded' => $version === null ? 0 : DownloadEvent::query()
                    ->whereIn('zip_build_id', ZipBuild::query()->where('gallery_version_id', $version->id)->select('id'))
                    ->count(),
                'viewed' => GalleryView::query()->where('gallery_id', $gallery->id)->count(),
            ]),
            'sets' => LiveUpdate::live($sets->map(fn ($set) => [
                'id' => $set->public_id,
                'name' => $set->name,
                'count' => $set->assets_count,
                'sortKey' => $set->sort_key,
                'sortDir' => $set->sort_dir,
                'density' => $densities[$set->id] ?? GalleryGridPref::DEFAULT_DENSITY,
                'access' => $set->access,
                'url' => $gallery->publicUrl().'?set='.$set->public_id,
            ])->all()),
            'assets' => $this->inArea(['bilder', 'gestaltung'], $bereich, fn () => $assets()->map(fn (Asset $asset) => [
                'id' => $asset->public_id,
                'key' => $asset->key,
                'setId' => $asset->set->public_id,
                'filename' => $asset->filename,
                'width' => $asset->width,
                'height' => $asset->height,
                'isCover' => $asset->id === $gallery->cover_asset_id,
                // The draft's own image route (GalleryAssetController::preview),
                // not the version-scoped guest one — a gallery the studio is
                // still assembling has no GalleryVersion for that route to
                // resolve against, and the grid must show pictures long
                // before the first publish.
                'src' => "/galerien/{$gallery->public_id}/bilder/{$asset->public_id}/vorschau",
            ])->all()),
            'versions' => $this->inArea(['einstellungen'], $bereich, fn () => $gallery->versions()->orderByDesc('version')->get()
                ->map(fn (GalleryVersion $row) => [
                    'version' => $row->version,
                    'publishedAt' => $row->published_at?->format('Y-m-d'),
                    'assetCount' => count($row->assets ?? []),
                ])->all()),
            'builds' => $this->inArea(['aktivitaet'], $bereich, fn () => $version === null ? [] : ZipBuild::query()
                ->where('gallery_version_id', $version->id)
                ->with('parts')
                ->get()
                ->map(fn (ZipBuild $build) => [
                    'id' => $build->public_id,
                    'size' => $build->size->value,
                    'status' => $build->status->value,
                    'parts' => $build->parts->count(),
                    'bytes' => (int) $build->parts->sum('byte_size'),
                ])->all()),
            // Lists carry their owner: gallery_lists.contact_id is set and not
            // nullable, so the sheet shows the person and their selections
            // rather than a heap of nameless picks.
            'lists' => $this->inArea(['einstellungen'], $bereich, fn () => GalleryList::query()
                ->where('gallery_id', $gallery->id)
                ->with(['contact', 'items'])
                ->get()
                ->map(fn (GalleryList $list) => [
                    'id' => $list->public_id,
                    'name' => $list->name,
                    'owner' => $list->contact?->name,
                    'count' => $list->items->count(),
                ])->all()),
            'activity' => $this->inArea(['aktivitaet'], $bereich, fn () => app(GalleryActivity::class)($gallery, $version)),
            // Aus der Aktivität verlinkt: nur die Bilder, die jemand markiert hat.
            'favoriteKeys' => $this->inArea(['bilder'], $bereich, fn () => request()->boolean('favoriten') && $version !== null
                ? AssetFavorite::query()->where('gallery_version_id', $version->id)->distinct()->pluck('asset_key')->all()
                : null),
            // G-04's "needs attention" tray, formerly the ingest screen's
            // own section — the pictures tab is the only place uploading
            // happens now, so this is where a failed upload gets noticed.
            'failedAssets' => $this->inArea(['bilder'], $bereich, fn () => FailedAssetData::collect(
                Asset::query()->where('gallery_id', $gallery->id)->where('status', AssetStatus::Failed)->oldest()->get(),
            )),
            // Kept out of figures.assets and sets[].count on purpose — those
            // stay the truth about pictures that exist; a number that moves
            // before anything was actually uploaded is worse than a second,
            // separate one (see the pictures pane's own pending section).
            'pendingUploads' => $this->inArea(['bilder'], $bereich, fn () => $pendingUploads()->map(fn (MultipartUpload $upload) => [
                'id' => $upload->public_id,
                'setId' => $upload->set->public_id,
                'filename' => $upload->filename,
                'stage' => $upload->status === MultipartUploadStatus::InProgress ? 'transferring' : 'processing',
            ])->all()),
        ]);
    }

    /**
     * Ein Wert, wenn sein Bereich offen ist, sonst ein Versprechen darauf.
     * Inertia holt ihn beim Bereichswechsel als Teillieferung nach, statt die
     * Aktivität und die ZIP-Archive bei jedem Blick auf die Bilder
     * mitzuschleppen.
     *
     * @param  list<string>  $areas
     */
    private function inArea(array $areas, string $current, \Closure $value): mixed
    {
        return in_array($current, $areas, true)
            ? LiveUpdate::live($value())
            : Inertia::optional($value)->live(on: LiveUpdate::EVENT, channel: LiveUpdate::topic());
    }

    /** The two secrets and the look, set where they are read. */
    public function update(Request $request, Gallery $gallery, UpdateGallery $updateGallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'slug' => [
                'sometimes', 'string',
                'min:'.GallerySlug::MIN, 'max:'.GallerySlug::MAX,
                'regex:'.GallerySlug::PATTERN,
                Rule::unique('galleries', 'slug')->where('prefix', $gallery->prefix)->ignore($gallery->id),
                Rule::notIn(GallerySlug::RESERVED),
            ],
            'view_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'download_pin' => ['nullable', 'string', 'min:4', 'max:64'],
            'requires_verified_email' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'external_url' => ['sometimes', 'url', 'max:2000'],
            'external_password' => ['nullable', 'string', 'max:200'],
            'info' => ['nullable', 'string', 'max:2000'],
            'design' => ['nullable', 'array'],
            ...GalleryDesign::rules('design'),
            'downloads_enabled' => ['sometimes', 'boolean'],
            'view_mode' => ['sometimes', Rule::in(['none', 'text', 'logo', 'locked'])],
            'watermark_text' => ['nullable', 'string', 'max:80'],
            'download_rule' => ['sometimes', Rule::in(['open', 'after_payment', 'off'])],
            'download_invoice_id' => ['nullable', 'string', Rule::exists('invoices', 'public_id')->where('case_id', $gallery->case_id)],
            'download_sizes' => ['sometimes', 'array', 'min:1'],
            'download_sizes.*' => ['string', Rule::enum(ZipBuildSize::class)],
            'expiry_reminder' => ['sometimes', 'boolean'],
            'lists_enabled' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
            ...GalleryPermissions::rules(),
            'intro_enabled' => ['sometimes', 'boolean'],
            'listed_on_home' => ['sometimes', 'boolean'],
            'home_pinned' => ['sometimes', 'boolean'],
            'project_type_id' => ['nullable', 'string', Rule::exists('project_types', 'public_id')->where('tenant_id', config('tenancy.tenant_id'))],
        ], [
            'slug.regex' => __('galleryRecord.slugFormat'),
            'slug.unique' => __('galleryRecord.slugTaken'),
            'slug.not_in' => __('galleryRecord.slugTaken'),
        ]);

        // Through rotate*(), never a plain write: the version bump is what
        // turns out every guest already inside, and it must not be
        // forgettable.
        if ($request->has('view_password')) {
            $gallery->rotateViewPassword($validated['view_password'] ?? null);
        }

        if ($request->has('download_pin')) {
            $gallery->rotateDownloadPin($validated['download_pin'] ?? null);
        }

        if ($request->has('requires_verified_email')) {
            $gallery->rotateEmailVerification($validated['requires_verified_email']);
        }

        $fields = array_intersect_key($validated, array_flip([
            'slug', 'expires_at', 'external_url', 'external_password', 'info', 'downloads_enabled', 'lists_enabled', 'comments_enabled', 'intro_enabled',
            'view_mode', 'watermark_text', 'download_rule', 'expiry_reminder', 'listed_on_home', 'home_pinned',
        ]));

        if ($request->has('project_type_id')) {
            $fields['project_type_id'] = $validated['project_type_id'] === null ? null
                : ProjectType::query()->where('public_id', $validated['project_type_id'])->value('id');
        }

        if ($request->has('download_sizes')) {
            $fields['download_sizes'] = array_values(array_unique($validated['download_sizes']));
        }

        if ($request->has('download_invoice_id')) {
            $fields['download_invoice_id'] = $validated['download_invoice_id'] === null ? null
                : Invoice::query()->where('public_id', $validated['download_invoice_id'])->value('id');
        }

        if ($request->has('permissions')) {
            $fields['permissions'] = array_replace_recursive(GalleryPermissions::normalize($gallery->permissions), $validated['permissions']);
        }

        if ($request->has('design')) {
            $fields['design'] = $validated['design'] ?? null;
        }

        $updateGallery($gallery, $fields);

        return back();
    }

    public function duplicate(Gallery $gallery, DuplicateGallery $duplicate): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);
        abort_if($gallery->is_external, 422);

        $copy = $duplicate($gallery, auth('staff')->user());

        return redirect()->route('galleries.show', $copy);
    }

    public function resumeCopy(Gallery $gallery, CopyGalleryAssets $copyAssets): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $copyAssets($gallery);

        return back();
    }

    public function destroy(Request $request, Gallery $gallery, DeleteGallery $delete): RedirectResponse
    {
        $this->authorize('delete', $gallery);

        $request->validate(['title' => filled($gallery->title) ? ['required', 'string', Rule::in([$gallery->title])] : ['nullable']]);

        $delete($gallery, auth('staff')->user());

        return redirect()->route('galleries.index');
    }
}
