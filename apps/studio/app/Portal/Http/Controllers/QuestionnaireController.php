<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Data\FormInstanceFieldData;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Http\Controllers\Controller;
use App\Notifications\Actions\NotifyStudio;
use App\Notifications\Studio\QuestionnaireSubmitted;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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

    public function show(string $project, string $questionnaire): Response
    {
        $contact = auth('portal')->user();
        $instance = $this->find($project, $questionnaire);

        return Inertia::render('Questionnaire', [
            'contact' => ['name' => $contact->name],
            'project' => ['id' => $instance->project->public_id, 'name' => $instance->project->name],
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
    public function update(Request $request, string $project, string $questionnaire): RedirectResponse
    {
        $contact = auth('portal')->user();
        $instance = $this->find($project, $questionnaire);

        $answers = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable', 'string', 'max:5000'],
        ])['answers'] ?? [];

        $this->assertRequiredAnswered($instance, $answers);

        $changed = 0;

        foreach ($instance->fields as $field) {
            $value = $this->normalise($answers[$field->public_id] ?? null);

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
                projectId: $instance->project_id,
            );

            // T-11. On any change, not only on the last one: a questionnaire
            // has no "handed in" state, so the studio is told the answers
            // moved and reads the current ones on the screen.
            ($this->notifyStudio)(new QuestionnaireSubmitted($instance));
        }

        return back();
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
            ->filter(fn (FormInstanceField $field) => $this->normalise($answers[$field->public_id] ?? null) === null)
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

    private function find(string $project, string $questionnaire): FormInstance
    {
        $found = auth('portal')->user()->visibleProjects()
            ->where('projects.public_id', $project)
            ->firstOrFail();

        return FormInstance::query()
            ->shared()
            ->where('project_id', $found->id)
            ->where('public_id', $questionnaire)
            ->with(['project', 'fields.currentAnswer'])
            ->firstOrFail();
    }
}
