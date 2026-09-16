<?php

namespace App\Templates\Http\Controllers;

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Data\FormTemplateData;
use App\Crm\Data\TaskTemplateData;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Shoot;
use App\Crm\Models\TaskTemplate;
use App\Crm\Support\CrmVocabulary;
use App\Documents\Data\DocumentTemplateData;
use App\Documents\Models\DocumentTemplate;
use App\Enums\Capability;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Models\GalleryPreset;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one room every kind of template is kept in. Before this, four kinds
 * lived in three menus — task and questionnaire templates under two different
 * settings groups, gallery presets under a third, document templates in the
 * sidebar — and a studio looking for "the thing we reuse" had to know which.
 *
 * Read-only: every write still goes to the controller that owns the kind,
 * with its own gate. What this screen adds is one list and one filter.
 */
class TemplateHubController extends Controller
{
    private const KINDS = ['aufgaben', 'fragebogen', 'dokumente', 'galerien'];

    public function index(Request $request, ApplyTaskTemplates $apply): Response
    {
        $validated = $request->validate([
            'typ' => ['nullable', Rule::in(self::KINDS)],
            'vorlage' => ['nullable', 'string'],
            'preview_date' => ['nullable', 'date'],
            'preview_shoot_kind' => ['nullable', 'string', 'max:255'],
        ]);

        $allowed = $this->allowedKinds();

        abort_if($allowed === [], 404);

        $kind = $validated['typ'] ?? null;

        abort_unless($kind === null || in_array($kind, $allowed, true), 404);

        $tasks = in_array('aufgaben', $allowed, true)
            ? TaskTemplate::query()->orderBy('sort_order')->get()
            : collect();
        $forms = in_array('fragebogen', $allowed, true)
            ? FormTemplate::query()->withCount('fields')->orderBy('name')->get()
            : collect();
        $documents = in_array('dokumente', $allowed, true)
            ? DocumentTemplate::current()->orderBy('name')->get()
            : collect();
        $presets = in_array('galerien', $allowed, true)
            ? GalleryPreset::query()->orderBy('name')->get()
            : collect();

        return Inertia::render('Templates/Index', [
            'kind' => $kind,
            'kinds' => $allowed,
            'counts' => [
                'aufgaben' => $tasks->count(),
                'fragebogen' => $forms->count(),
                'dokumente' => $documents->count(),
                'galerien' => $presets->count(),
            ],
            'taskTemplates' => TaskTemplateData::collect($tasks),
            'formTemplates' => FormTemplateData::collect($forms),
            'documentTemplates' => DocumentTemplateData::collect($documents),
            'galleryPresets' => GalleryPresetData::collect($presets),
            'audiences' => $this->options(TaskAudience::cases()),
            'shootKinds' => Shoot::kindSuggestions(),
            'projectTypes' => app(CrmVocabulary::class)->types()
                ->filter(fn (ProjectType $type) => $type->is_active)
                ->map(fn (ProjectType $type) => ['value' => $type->key, 'label' => $type->name])
                ->values()->all(),
            'selected' => $validated['vorlage'] ?? null,
            'previewDate' => $validated['preview_date'] ?? null,
            'previewShootKind' => $validated['preview_shoot_kind'] ?? null,
            'preview' => isset($validated['preview_date']) && in_array('aufgaben', $allowed, true)
                ? $apply->preview(
                    CarbonImmutable::parse($validated['preview_date']),
                    $validated['preview_shoot_kind'] ?? null,
                )
                : null,
        ]);
    }

    /**
     * The kinds this staff member may see, in menu order. Each keeps the gate
     * its own screen had: tasks and questionnaires their capability policies,
     * documents theirs, gallery presets the settings capability.
     *
     * @return list<string>
     */
    private function allowedKinds(): array
    {
        $checks = [
            'aufgaben' => Gate::allows('viewAny', TaskTemplate::class),
            'fragebogen' => Gate::allows('viewAny', FormTemplate::class),
            'dokumente' => Gate::allows('viewAny', DocumentTemplate::class),
            'galerien' => auth('staff')->user()?->role->allows(Capability::ManageSettings) ?? false,
        ];

        return array_keys(array_filter($checks));
    }

    /**
     * @param  list<TaskAudience>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return collect($cases)
            ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
            ->all();
    }
}
