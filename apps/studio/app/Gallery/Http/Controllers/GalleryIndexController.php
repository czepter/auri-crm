<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Models\Project;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Data\GalleryRowData;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die Liste, die es bis #255 nicht gab: eine Galerie war nur über ihr Projekt
 * oder ihren Kontakt erreichbar, und in der Nav stand sie gar nicht.
 *
 * Heißt GalleryIndexController und nicht GalleryController, weil den Namen
 * bereits App\Portal\Http\Controllers\GalleryController trägt und, für
 * dieselbe Entität, GalleryRecordController.
 */
class GalleryIndexController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Gallery::class);

        $galleries = Gallery::query()
            ->with(['project', 'currentVersion'])
            ->withCount(['assetRows as asset_count' => fn ($query) => $query->inDraft()])
            // Die eine Ausnahme von der Projektsichtbarkeit: eine Galerie ohne
            // Buchung hat nichts, woran ein Filter greifen könnte.
            ->where(fn ($query) => $query
                ->whereNull('project_id')
                ->orWhereIn('project_id', Project::query()->visibleTo(auth('staff')->user())->select('id')))
            ->latest()
            ->get();

        return Inertia::render('Galleries/Index', [
            'galleries' => GalleryRowData::collect($galleries),
        ]);
    }

    public function create(StudioSettingsResolver $settings): Response
    {
        $this->authorize('create', Gallery::class);

        return Inertia::render('Galleries/Create', [
            'presets' => GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get()),
            // Die unterste Stufe der Geburtskette; die beiden darüber reichen
            // Vorlage und Projektvorschlag nach.
            'studioDefaults' => [
                'downloads' => $settings->galleryDownloadsEnabled(),
                'lists' => $settings->galleryListsEnabled(),
                'comments' => $settings->galleryCommentsEnabled(),
                'expiryDays' => $settings->galleryDefaults()['expiryDays'],
                'requiresSettledBalance' => $settings->galleryDefaults()['requiresSettledBalance'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Gallery::class);

        $validated = $request->validate([
            // Ueber visibleTo aufgeloest, wie der Index: was diese Rolle nicht
            // sieht, ist kein Ziel. findByPublicId lieferte auf einen
            // Tippfehler null und damit still eine projektlose Galerie.
            'project_id' => ['nullable', 'string', Rule::exists('projects', 'public_id')],
            'title' => ['required', 'string', 'max:200'],
            'is_external' => ['required', 'boolean'],
            'external_url' => ['nullable', 'url', 'max:2000', 'required_if:is_external,true'],
            'expires_at' => ['nullable', 'date'],
            'requires_settled_balance' => ['nullable', 'boolean'],
            'downloads_enabled' => ['nullable', 'boolean'],
            'lists_enabled' => ['nullable', 'boolean'],
            'comments_enabled' => ['nullable', 'boolean'],
            'design' => ['nullable', 'array'],
        ]);

        $project = ($validated['project_id'] ?? null) === null
            ? null
            : Project::query()
                ->visibleTo(auth('staff')->user())
                ->where('public_id', $validated['project_id'])
                ->firstOrFail();

        $gallery = Gallery::create([
            ...Arr::except($validated, ['project_id']),
            'project_id' => $project?->id,
        ]);

        AuditLogger::record(
            event: 'gallery.created',
            actor: auth('staff')->user(),
            context: ['title' => $gallery->title],
            subject: $gallery->public_id,
        );

        // Eine externe Galerie hat keinen Ingest — dort liegt nichts, was man
        // hochladen könnte.
        return redirect($gallery->is_external
            ? "/galerien/{$gallery->public_id}"
            : "/galerien/{$gallery->public_id}/medien");
    }
}
