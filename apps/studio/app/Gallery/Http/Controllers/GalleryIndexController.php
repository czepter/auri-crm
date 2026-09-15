<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Data\ProjectSuggestionData;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Data\GalleryRowData;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Http\Controllers\Controller;
use App\Settings\Data\GallerySettingsData;
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
    public function index(StudioSettingsResolver $settings): Response
    {
        $this->authorize('viewAny', Gallery::class);

        // G-14: die Vorgaben reisen mit der Liste, weil der Raum ueber ihr
        // aufgeht. Wer sie nicht bearbeiten darf, bekommt sie nicht — die
        // Pille im Kopf haengt an genau diesem null.
        $mayManage = auth('staff')->user()?->role->allows(Capability::ManageSettings) ?? false;

        $galleries = Gallery::query()
            ->with(['project', 'currentVersion'])
            ->withCount(['assets as asset_count' => fn ($query) => $query->inDraft()])
            // Die eine Ausnahme von der Projektsichtbarkeit: eine Galerie ohne
            // Buchung hat nichts, woran ein Filter greifen könnte.
            ->where(fn ($query) => $query
                ->whereNull('project_id')
                ->orWhereIn('project_id', Project::query()->visibleTo(auth('staff')->user())->select('id')))
            ->latest()
            ->get();

        return Inertia::render('Galleries/Index', [
            'galleries' => GalleryRowData::collect($galleries),
            'settings' => $mayManage ? GallerySettingsData::fromResolver($settings) : null,
            'presets' => $mayManage
                ? GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get())
                : [],
            // Die Adresszeile der Vorschau. Ohne Slug — welche Galerie das
            // spaeter wird, entscheidet sich beim Anlegen.
            'galleryHost' => preg_replace('#^https?://#', '', rtrim((string) config('services.gallery.url'), '/')),
        ]);
    }

    public function create(Request $request, StudioSettingsResolver $settings): Response
    {
        $this->authorize('create', Gallery::class);

        $project = $request->filled('projekt')
            ? Project::query()
                ->visibleTo(auth('staff')->user())
                ->where('public_id', $request->query('projekt'))
                ->first()
            : null;

        return Inertia::render('Galleries/Create', [
            'project' => $project ? ProjectSuggestionData::fromModel($project) : null,
            'shootId' => $project?->shoots()->where('public_id', $request->query('shooting'))->value('public_id'),
            'presets' => GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get()),
            // Die unterste Stufe der Geburtskette; die beiden darüber reichen
            // Vorlage und Projektvorschlag nach.
            'studioDefaults' => [
                'downloads' => $settings->galleryDownloadsEnabled(),
                'lists' => $settings->galleryListsEnabled(),
                'comments' => $settings->galleryCommentsEnabled(),
                'expiryDays' => $settings->galleryDefaults()['expiryDays'],
                'design' => $settings->galleryDesign(),
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
            'shoot_id' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'is_external' => ['required', 'boolean'],
            'external_url' => ['nullable', 'url', 'max:2000', 'required_if:is_external,true'],
            'expires_at' => ['nullable', 'date'],
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
            ...Arr::except($validated, ['project_id', 'shoot_id']),
            'project_id' => $project?->id,
            'shoot_id' => isset($validated['shoot_id'])
                ? $project?->shoots()->where('public_id', $validated['shoot_id'])->value('id')
                : null,
        ]);

        AuditLogger::record(
            event: 'gallery.created',
            actor: auth('staff')->user(),
            context: ['title' => $gallery->title],
            subject: $gallery->public_id,
        );

        return redirect("/galerien/{$gallery->public_id}");
    }
}
