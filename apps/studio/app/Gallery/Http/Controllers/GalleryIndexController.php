<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Data\CaseSuggestionData;
use App\Crm\Models\ClientCase;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\Capability;
use App\Gallery\Actions\CreateGallery;
use App\Gallery\Actions\CreateProjectGallery;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Data\GalleryRowData;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Http\Controllers\Controller;
use App\Settings\Data\GallerySettingsData;
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

        // G-14: die Vorgaben reisen mit der Liste, weil der Raum ueber ihr
        // aufgeht. Wer sie nicht bearbeiten darf, bekommt sie nicht — die
        // Pille im Kopf haengt an genau diesem null.
        $mayManage = auth('staff')->user()?->can(Capability::ManageSettings->value) ?? false;

        $galleries = Gallery::query()
            ->with(['case', 'currentVersion', 'coverAsset'])
            ->withCount(['assets as asset_count' => fn ($query) => $query->inDraft()])
            // Die eine Ausnahme von der Projektsichtbarkeit: eine Galerie ohne
            // Buchung hat nichts, woran ein Filter greifen könnte.
            ->where(fn ($query) => $query
                ->whereNull('case_id')
                ->orWhereIn('case_id', ClientCase::query()->visibleTo(auth('staff')->user())->select('id')))
            ->latest()
            ->get();

        return Inertia::render('Galleries/Index', [
            'galleries' => GalleryRowData::collect($galleries),
            'settings' => $mayManage ? GallerySettingsData::current() : null,
            'presets' => $mayManage
                ? GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get())
                : [],
            'mailTemplates' => $mayManage ? self::mailTemplates() : [],
            // Die Adresszeile der Vorschau. Ohne Slug — welche Galerie das
            // spaeter wird, entscheidet sich beim Anlegen.
            'galleryHost' => preg_replace('#^https?://#', '', rtrim((string) config('services.gallery.url'), '/')),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Gallery::class);

        $project = $request->filled('projekt')
            ? ClientCase::query()
                ->visibleTo(auth('staff')->user())
                ->where('public_id', $request->query('projekt'))
                ->first()
            : null;

        $siblings = $project
            ? Gallery::query()
                ->with(['case', 'currentVersion', 'coverAsset'])
                ->withCount(['assets as asset_count' => fn ($query) => $query->inDraft()])
                ->where('case_id', $project->id)
                ->latest()
                ->get()
            : collect();

        $host = preg_replace('#^https?://#', '', rtrim((string) config('services.gallery.url'), '/'));

        return Inertia::render('Galleries/Create', [
            'siblings' => GalleryRowData::collect($siblings),
            // Das Präfix teilen sich alle Galerien eines Projekts; den Slug vergibt erst das Anlegen.
            'address' => $siblings->isEmpty() ? $host : $host.'/'.$siblings->first()->prefix,
            'mode' => $request->query('art') === 'extern' ? 'external' : 'internal',
            'project' => $project ? CaseSuggestionData::fromModel($project) : null,
            'shootId' => $project?->shoots()->where('public_id', $request->query('shooting'))->value('public_id'),
        ]);
    }

    public function store(Request $request, CreateGallery $createGallery, CreateProjectGallery $createProjectGallery): RedirectResponse
    {
        $this->authorize('create', Gallery::class);

        $validated = $request->validate([
            // Ueber visibleTo aufgeloest, wie der Index: was diese Rolle nicht
            // sieht, ist kein Ziel. findByPublicId lieferte auf einen
            // Tippfehler null und damit still eine projektlose Galerie.
            'case_id' => ['nullable', 'string', Rule::exists('cases', 'public_id')],
            'shoot_id' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'is_external' => ['required', 'boolean'],
            'external_url' => ['nullable', 'url', 'max:2000', 'required_if:is_external,true'],
            'info' => ['nullable', 'string', 'max:2000'],
            'external_password' => ['nullable', 'string', 'max:200'],
        ]);

        $project = ($validated['case_id'] ?? null) === null
            ? null
            : ClientCase::query()
                ->visibleTo(auth('staff')->user())
                ->where('public_id', $validated['case_id'])
                ->firstOrFail();

        $external = $validated['is_external'];

        $gallery = $project && ! $external
            ? $createProjectGallery($project, $validated['shoot_id'] ?? null, auth('staff')->user(), $validated['title'])
            : $createGallery(
                Arr::except($validated, ['case_id', 'shoot_id']),
                $project,
                $validated['shoot_id'] ?? null,
                auth('staff')->user(),
                $external ? null : GalleryPreset::query()->where('is_default', true)->first(),
            );

        return redirect("/galerien/{$gallery->public_id}");
    }

    /** @return list<array{id: string, name: string}> die Mail-Vorlagen, die eine Galerie-Vorlage wählen kann (nach Familie, nicht nach Fassung) */
    public static function mailTemplates(): array
    {
        return DocumentTemplate::query()->current()->ofType(DocumentTemplateType::Mail)->orderBy('name')->get()
            ->map(fn (DocumentTemplate $template) => ['id' => $template->family_id, 'name' => $template->name])
            ->all();
    }
}
