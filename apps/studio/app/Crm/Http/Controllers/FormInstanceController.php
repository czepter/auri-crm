<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\SeedFormInstanceFromTemplate;
use App\Crm\Data\CaseSuggestionData;
use App\Crm\Data\FormInstanceData;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\FormInstanceFile;
use App\Crm\Models\FormTemplate;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E-02: seeding and personalising a project's own questionnaires. Shaped
 * after App\Quotes\Http\Controllers\QuoteController — store() optionally
 * seeds from a template the same way it seeds a quote from a package,
 * update() wholesale-replaces the fields the same way FormTemplateController
 * does for a template's own.
 */
class FormInstanceController extends Controller
{
    /**
     * The Übersicht's wizard: the project or inquiry, then a template, then the
     * copy to personalise. The copy is named in the address so a reload keeps it.
     */
    public function create(Request $request): Response
    {
        $viewer = $request->user('staff');
        abort_unless($viewer->can(Capability::ManageFormTemplates->value), 403);

        $case = $request->filled('projekt')
            ? ClientCase::query()->visibleTo($viewer)->where('public_id', $request->query('projekt'))->first()
            : null;

        $instance = $case && $request->filled('fragebogen')
            ? $case->formInstances()->where('public_id', $request->query('fragebogen'))->with('fields.currentAnswer')->first()
            : null;

        return Inertia::render('Forms/Create', [
            'project' => $case ? CaseSuggestionData::fromModel($case) : null,
            'formTemplates' => FormTemplate::active()->orderBy('name')->get()
                ->map(fn (FormTemplate $template) => ['value' => $template->public_id, 'label' => $template->name])
                ->all(),
            'fieldTypes' => collect(FormFieldType::cases())
                ->map(fn (FormFieldType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
            'instance' => $instance ? FormInstanceData::from($instance) : null,
        ]);
    }

    public function store(Request $request, ClientCase $case, SeedFormInstanceFromTemplate $seed): RedirectResponse
    {
        $this->authorize('manageForms', $case);

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

        $instance = $case->formInstances()->create([
            'name' => $template?->name ?? 'Neuer Fragebogen',
            'source_template_name' => $template?->name,
        ]);

        if ($template) {
            $seed($instance, $template);
        }

        AuditLogger::record(
            event: 'form_instance.created',
            actor: auth('staff')->user(),
            context: ['project' => $case->public_id, 'template' => $template?->public_id],
            subject: $instance->public_id,
            projectId: $case->id,
        );

        return $request->boolean('wizard')
            ? redirect('/frageboegen/neu?'.http_build_query(['projekt' => $case->public_id, 'fragebogen' => $instance->public_id]))
            : back();
    }

    /**
     * Wholesale replace rather than a diffed sync — the same shape
     * FormTemplateController::update() uses, and for the same reason: no id
     * anywhere else references a form instance field row, so there is no
     * foreign key a delete-and-recreate could orphan.
     */
    public function update(Request $request, FormInstance $formInstance): RedirectResponse
    {
        $this->authorize('manageForms', $formInstance->case);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fields' => ['array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.required' => ['required', 'boolean'],
            ...$this->optionRules($request),
        ]);

        $formInstance->update(['name' => $validated['name']]);

        $formInstance->fields()->delete();
        $formInstance->fields()->createMany($this->fieldRows($validated['fields']));

        AuditLogger::record(
            event: 'form_instance.updated',
            actor: auth('staff')->user(),
            context: ['name' => $formInstance->name],
            subject: $formInstance->public_id,
            projectId: $formInstance->case_id,
        );

        return back();
    }

    /**
     * P-03 (#222): put this questionnaire in front of the client, or take it
     * back. Per questionnaire rather than per project, because a studio's own
     * checklists sit on the same table — see FormInstance::isShared().
     *
     * Un-sharing hides the screen; it never deletes an answer the client
     * already gave. form_answers is append-only and stays that way.
     */
    public function share(Request $request, FormInstance $formInstance): RedirectResponse
    {
        $this->authorize('manageForms', $formInstance->case);

        $shared = $request->validate(['shared' => ['required', 'boolean']])['shared'];

        $formInstance->forceFill(['shared_at' => $shared ? now() : null])->save();

        AuditLogger::record(
            event: $shared ? 'form_instance.shared' : 'form_instance.unshared',
            actor: auth('staff')->user(),
            subject: $formInstance->public_id,
            projectId: $formInstance->case_id,
        );

        return back();
    }

    /**
     * E-03: staff recording (or correcting) the answer to one question on an
     * already-seeded instance. Never updates a FormAnswer in place — always
     * FormInstanceField::answer(), which appends the next version and leaves
     * every earlier one exactly as it was.
     */
    public function answer(Request $request, FormInstanceField $formInstanceField): RedirectResponse
    {
        $this->authorize('manageForms', $formInstanceField->formInstance->case);

        $validated = $request->validate([
            'value' => ['nullable', 'string', 'max:20000'],
        ]);

        $value = $formInstanceField->type === FormFieldType::GroupPhotos
            ? FormInstanceField::normaliseGroupPhotos($validated['value'] ?? null)
            : $validated['value'] ?? null;

        $formInstanceField->answer($value, auth('staff')->user());

        AuditLogger::record(
            event: 'form_instance_field.answered',
            actor: auth('staff')->user(),
            context: ['formInstance' => $formInstanceField->formInstance->public_id],
            subject: $formInstanceField->public_id,
            projectId: $formInstanceField->formInstance->case_id,
        );

        return back();
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
     * E-03: a file answering a "Datei" field. Type and size come from the
     * field's own configuration, not from the request — see
     * FormInstanceField::fileRules().
     */
    public function upload(Request $request, FormInstanceField $formInstanceField): RedirectResponse
    {
        $this->authorize('manageForms', $formInstanceField->formInstance->case);

        abort_unless($formInstanceField->type === FormFieldType::File, 404);

        $request->validate(['file' => $formInstanceField->fileRules()]);

        $file = $formInstanceField->attachFile($request->file('file'), auth('staff')->user());

        AuditLogger::record(
            event: 'form_instance_field.file_uploaded',
            actor: auth('staff')->user(),
            context: ['formInstance' => $formInstanceField->formInstance->public_id, 'name' => $file->name],
            subject: $formInstanceField->public_id,
            projectId: $formInstanceField->formInstance->case_id,
        );

        return back();
    }

    /** A real delete, bytes included: form_answers is the append-only ledger, a client's file is not. */
    public function download(FormInstanceFile $file): StreamedResponse
    {
        $this->authorize('manageForms', $file->formInstanceField->formInstance->case);

        return Storage::disk('local')->download($file->path, $file->name);
    }

    public function destroyFile(FormInstanceFile $file): RedirectResponse
    {
        $instance = $file->formInstanceField->formInstance;

        $this->authorize('manageForms', $instance->case);

        $name = $file->name;
        $file->purge();

        AuditLogger::record(
            event: 'form_instance_field.file_deleted',
            actor: auth('staff')->user(),
            context: ['formInstance' => $instance->public_id, 'name' => $name],
            subject: $file->formInstanceField->public_id,
            projectId: $instance->case_id,
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
        // sortKeys because validated() hands the rows back in rule order, not
        // in list order, once a row carries its own type-dependent options rules.
        return collect($fields)->sortKeys()->values()->map(fn (array $field, int $index) => [
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => $field['required'],
            'options' => $field['options'] ?? null,
            'sort_order' => $index,
        ])->all();
    }
}
