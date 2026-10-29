<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Actions\NotifyStudio;
use App\Crm\Data\FormInstanceFieldData;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\FormInstanceFile;
use App\Http\Controllers\Controller;
use App\Portal\Notifications\Studio\QuestionnaireSubmitted;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P-03 (#222)'s last open piece. The inquiry screen linked to a
 * questionnaire nobody could fill in: E-02 and E-03 built the whole family
 * admin-only, so the only way a client's answers ever reached the studio was
 * somebody at the studio typing them.
 *
 * The client never edits the questions, only answers them — the reverse of
 * FormInstanceController, which edits the questions and may correct an
 * answer. Both go through FormInstanceField::answer(), which appends a
 * version and touches nothing already written; a client revising their own
 * answer and staff correcting it are the same append, distinguished by who
 * the row names.
 *
 * Reachable only for a questionnaire the studio has actually shared
 * (form_instances.shared_at) on a booking the contact is on. A 404
 * otherwise, in both cases, same rule as everywhere on this surface: a
 * studio's internal checklist lives on the same table and is nobody else's
 * business.
 */
class QuestionnaireController extends Controller
{
    public function __construct(private readonly NotifyStudio $notifyStudio) {}

    public function show(string $case, string $questionnaire): Response
    {
        $contact = auth('portal')->user();
        $instance = $this->find($case, $questionnaire);

        return Inertia::render('Questionnaire', [
            'contact' => ['name' => $contact->name],
            'project' => ['id' => $instance->case->public_id, 'name' => $instance->case->name],
            'questionnaire' => [
                'id' => $instance->public_id,
                'name' => $instance->name,
                'fields' => $instance->fields->map(FormInstanceFieldData::fromModel(...))->all(),
            ],
        ]);
    }

    /**
     * One submit for the whole questionnaire, not one request per field: a
     * client answers a form, they do not save eight of them. Only fields
     * whose value actually moved are appended — re-submitting an unchanged
     * questionnaire must not put a second identical version on every row.
     */
    public function update(Request $request, string $case, string $questionnaire): RedirectResponse
    {
        $contact = auth('portal')->user();
        $instance = $this->find($case, $questionnaire);

        $answers = $request->validate([
            'answers' => ['array'],
            // 20k rather than 5k because a Gruppenfotos plan travels as JSON
            // through this same map; FormInstanceField::normaliseGroupPhotos()
            // caps what actually survives.
            'answers.*' => ['nullable', 'string', 'max:20000'],
        ])['answers'] ?? [];

        $this->assertRequiredAnswered($instance, $answers);

        $changed = 0;

        foreach ($instance->fields as $field) {
            if ($field->type === FormFieldType::File) {
                continue;
            }

            $value = $this->valueFor($field, $answers);

            if ($value === $field->currentAnswer?->value) {
                continue;
            }

            $field->answer($value, $contact);
            $changed++;
        }

        if ($changed > 0) {
            AuditLogger::record(
                event: 'portal.questionnaire.answered',
                context: ['contact' => $contact->public_id, 'fields' => $changed],
                subject: $instance->public_id,
                projectId: $instance->case_id,
            );

            // T-11. On any change, not only on the last one: a questionnaire
            // has no "handed in" state, so the studio is told the answers
            // moved and reads the current ones on the screen.
            ($this->notifyStudio)(new QuestionnaireSubmitted($instance));
        }

        return back();
    }

    /**
     * A file answering a question. Uploaded on its own rather than with the
     * form's one submit — bytes are not something a client retypes when the
     * rest of the page fails validation.
     */
    public function download(string $case, string $questionnaire, string $file): StreamedResponse
    {
        $instance = $this->find($case, $questionnaire);

        $found = FormInstanceFile::query()
            ->whereIn('form_instance_field_id', $instance->fields->pluck('id'))
            ->where('public_id', $file)
            ->firstOrFail();

        return Storage::disk('local')->download($found->path, $found->name);
    }

    public function upload(Request $request, string $case, string $questionnaire): RedirectResponse
    {
        $contact = auth('portal')->user();
        $instance = $this->find($case, $questionnaire);

        $field = $instance->fields
            ->firstWhere('public_id', $request->input('field'));

        abort_if($field === null || $field->type !== FormFieldType::File, 404);

        $request->validate(['file' => $field->fileRules()]);

        $file = $field->attachFile($request->file('file'), $contact);

        AuditLogger::record(
            event: 'portal.questionnaire.file_uploaded',
            context: ['contact' => $contact->public_id, 'name' => $file->name],
            subject: $instance->public_id,
            projectId: $instance->case_id,
        );

        ($this->notifyStudio)(new QuestionnaireSubmitted($instance));

        return back();
    }

    /** Scoped through the questionnaire, so a file id from another booking is a 404 and not a delete. */
    public function destroyFile(string $case, string $questionnaire, string $file): RedirectResponse
    {
        $contact = auth('portal')->user();
        $instance = $this->find($case, $questionnaire);

        $row = FormInstanceFile::query()
            ->where('public_id', $file)
            ->whereIn('form_instance_field_id', $instance->fields->pluck('id'))
            ->firstOrFail();

        $name = $row->name;
        $row->purge();

        AuditLogger::record(
            event: 'portal.questionnaire.file_deleted',
            context: ['contact' => $contact->public_id, 'name' => $name],
            subject: $instance->public_id,
            projectId: $instance->case_id,
        );

        return back();
    }

    /**
     * What one field's answer is worth once its own type has had a look at
     * it. Both the required check and the write go through here, so a
     * Gruppenfotos plan that normalises to nothing counts as unanswered.
     */
    private function valueFor(FormInstanceField $field, array $answers): ?string
    {
        $raw = $answers[$field->public_id] ?? null;

        return $field->type === FormFieldType::GroupPhotos
            ? FormInstanceField::normaliseGroupPhotos(is_string($raw) ? $raw : null)
            : $this->normalise($raw);
    }

    /**
     * A required question is required of the client too, and the check lives
     * here rather than in the validate() call above because the rules are
     * per row of data this request does not name — the fields come from the
     * questionnaire, never from the payload.
     */
    private function assertRequiredAnswered(FormInstance $instance, array $answers): void
    {
        $missing = $instance->fields
            ->filter(fn (FormInstanceField $field) => $field->required)
            ->filter(fn (FormInstanceField $field) => $field->type === FormFieldType::File
                ? $field->files->isEmpty()
                : $this->valueFor($field, $answers) === null)
            ->mapWithKeys(fn (FormInstanceField $field) => [
                "answers.{$field->public_id}" => __('validation.required', ['attribute' => $field->label]),
            ])
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    /** An empty box is an unanswered question, not the string "". */
    private function normalise(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function find(string $case, string $questionnaire): FormInstance
    {
        $found = auth('portal')->user()->visibleProjects()
            ->where('cases.public_id', $case)
            ->firstOrFail();

        return FormInstance::query()
            ->shared()
            ->where('case_id', $found->id)
            ->where('public_id', $questionnaire)
            ->with(['case', 'fields.currentAnswer', 'fields.files'])
            ->firstOrFail();
    }
}
