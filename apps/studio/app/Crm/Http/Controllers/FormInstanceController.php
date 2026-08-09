<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\SeedFormInstanceFromTemplate;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * E-02: seeding and personalising a project's own questionnaires. Shaped
 * after App\Quotes\Http\Controllers\QuoteController — store() optionally
 * seeds from a template the same way it seeds a quote from a package,
 * update() wholesale-replaces the fields the same way FormTemplateController
 * does for a template's own.
 */
class FormInstanceController extends Controller
{
    public function store(Request $request, Project $project, SeedFormInstanceFromTemplate $seed): RedirectResponse
    {
        $this->authorize('manageForms', $project);

        $validated = $request->validate([
            // Scoped to active templates for the same reason QuoteController
            // scopes its package option: a deactivated template dropping out
            // of what a new instance may start from (FormTemplate::scopeActive())
            // is a validation fact about this form's input, not a missing-record error.
            'template' => ['nullable', 'string', Rule::exists('form_templates', 'public_id')->where('is_active', true)],
        ]);

        $template = ! empty($validated['template'])
            ? FormTemplate::active()->where('public_id', $validated['template'])->firstOrFail()
            : null;

        $instance = $project->formInstances()->create([
            'name' => $template?->name ?? 'Neuer Fragebogen',
            'source_template_name' => $template?->name,
        ]);

        if ($template) {
            $seed($instance, $template);
        }

        AuditLogger::record(
            event: 'form_instance.created',
            actor: auth('staff')->user(),
            context: ['project' => $project->public_id, 'template' => $template?->public_id],
            subject: $instance->public_id,
        );

        return back();
    }

    /**
     * Wholesale replace rather than a diffed sync — the same shape
     * FormTemplateController::update() uses, and for the same reason: no id
     * anywhere else references a form instance field row, so there is no
     * foreign key a delete-and-recreate could orphan.
     */
    public function update(Request $request, FormInstance $formInstance): RedirectResponse
    {
        $this->authorize('manageForms', $formInstance->project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fields' => ['array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['string', 'max:255'],
        ]);

        $formInstance->update(['name' => $validated['name']]);

        $formInstance->fields()->delete();
        $formInstance->fields()->createMany($this->fieldRows($validated['fields']));

        AuditLogger::record(
            event: 'form_instance.updated',
            actor: auth('staff')->user(),
            context: ['name' => $formInstance->name],
            subject: $formInstance->public_id,
        );

        return back();
    }

    /**
     * sort_order comes from list position — the same reason
     * FormTemplateController::fieldRows() gives.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private function fieldRows(array $fields): array
    {
        return collect($fields)->values()->map(fn (array $field, int $index) => [
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => $field['required'],
            'options' => $field['options'] ?? null,
            'sort_order' => $index,
        ])->all();
    }
}
