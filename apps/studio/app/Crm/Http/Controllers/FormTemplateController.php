<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\FormTemplateData;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\ProjectType;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E-01: CRUD over the studio's reusable FormTemplates. Gates on
 * Capability::ManageFormTemplates (FormTemplatePolicy) rather than
 * ManageSettings, the same reasoning T-14 gives for ManageTasks — authoring a
 * client questionnaire is its own domain of work, done in the settings room.
 */
class FormTemplateController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', FormTemplate::class);

        return Inertia::render('Settings/FormTemplates/Create', [
            'projectTypes' => $this->projectTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', FormTemplate::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'project_type' => ['nullable', Rule::exists('project_types', 'key')->where('is_active', true)],
        ]);

        $template = FormTemplate::create($validated);

        AuditLogger::record(
            event: 'form_template.created',
            actor: auth('staff')->user(),
            context: ['name' => $template->name],
            subject: $template->public_id,
        );

        return redirect("/einstellungen/fragebogen-vorlagen/{$template->public_id}");
    }

    public function edit(FormTemplate $formTemplate): Response
    {
        $this->authorize('update', $formTemplate);

        return Inertia::render('Settings/FormTemplates/Edit', [
            'template' => FormTemplateData::fromModel($formTemplate),
            'projectTypes' => $this->projectTypeOptions(),
            'fieldTypes' => collect(FormFieldType::cases())
                ->map(fn (FormFieldType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    public function update(Request $request, FormTemplate $formTemplate): RedirectResponse
    {
        $this->authorize('update', $formTemplate);

        $validated = $this->validated($request);

        $formTemplate->update([
            'name' => $validated['name'],
            'project_type' => $validated['project_type'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        // Wholesale replace rather than a diffed sync — same shape as
        // Package::items() on update. No FormInstance exists yet (E-02) to
        // hold a reference a delete-and-recreate could orphan.
        $formTemplate->fields()->delete();
        $formTemplate->fields()->createMany($this->fieldRows($validated['fields']));

        AuditLogger::record(
            event: 'form_template.updated',
            actor: auth('staff')->user(),
            context: ['name' => $formTemplate->name],
            subject: $formTemplate->public_id,
        );

        return redirect("/einstellungen/fragebogen-vorlagen/{$formTemplate->public_id}");
    }

    public function destroy(FormTemplate $formTemplate): RedirectResponse
    {
        $this->authorize('delete', $formTemplate);

        $formTemplate->delete();

        AuditLogger::record(
            event: 'form_template.deleted',
            actor: auth('staff')->user(),
            context: ['name' => $formTemplate->name],
            subject: $formTemplate->public_id,
        );

        return redirect('/einstellungen/fragebogen-vorlagen');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'project_type' => ['nullable', Rule::exists('project_types', 'key')->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
            'fields' => ['array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            ...$this->optionRules($request),
        ]);
    }

    /**
     * The options column carries three shapes — select's choices, a file
     * field's upload config, a Gruppenfotos builder configuration — so the
     * rules for it depend on the type in the same row. See
     * FormFieldType::optionRules().
     */
    private function optionRules(Request $request): array
    {
        $fields = (array) $request->input('fields', []);

        return collect(array_keys($fields))
            ->mapWithKeys(function (int|string $index) use ($request) {
                $type = $request->input("fields.{$index}.type");

                return (FormFieldType::tryFrom(is_string($type) ? $type : '') ?? FormFieldType::Text)
                    ->optionRules("fields.{$index}");
            })
            ->all();
    }

    /**
     * sort_order comes from list position, the same reason
     * PackageController::itemRows() gives: the builder reorders by moving a
     * row, never by typing a number.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private function fieldRows(array $fields): array
    {
        // sortKeys because validated() hands the rows back in rule order, not
        // in list order, once a row carries its own type-dependent options rules.
        return collect($fields)->sortKeys()->values()->map(fn (array $field, int $index) => [
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => $field['required'],
            'help' => filled($field['help'] ?? null) ? trim($field['help']) : null,
            'options' => $field['options'] ?? null,
            'sort_order' => $index,
        ])->all();
    }

    private function projectTypeOptions(): array
    {
        return app(CrmVocabulary::class)->types()
            ->filter(fn (ProjectType $type) => $type->is_active)
            ->map(fn (ProjectType $type) => ['value' => $type->key, 'label' => $type->name])
            ->values()->all();
    }
}
