<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Enums\DueAnchor;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\TaskTemplate;
use App\Crm\Rules\StageOfPipeline;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * T-14: CRUD over the studio's standing task templates, and a preview of what
 * the active set produces for a given shoot date. Sits in the settings
 * section but gates on Capability::ManageTasks rather than ManageSettings
 * (design doc §3 D4) — editing what a booked shoot owes is the same work as
 * managing tasks, done in a different room. That is why this uses
 * $this->authorize() and ordinary 403s (TaskTemplatePolicy), rather than
 * AuthorizesStaffSettings's 404-not-403 gate every other T-0x settings
 * screen uses.
 */
class TaskTemplateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TaskTemplate::class);

        $template = TaskTemplate::create($this->validated($request));

        AuditLogger::record(
            event: 'task_template.created',
            actor: auth('staff')->user(),
            context: ['title' => $template->title],
            subject: $template->public_id,
        );

        return back();
    }

    public function update(Request $request, TaskTemplate $taskTemplate): RedirectResponse
    {
        $this->authorize('update', $taskTemplate);

        $taskTemplate->update($this->validated($request));

        AuditLogger::record(
            event: 'task_template.updated',
            actor: auth('staff')->user(),
            context: ['title' => $taskTemplate->title],
            subject: $taskTemplate->public_id,
        );

        return back();
    }

    public function destroy(TaskTemplate $taskTemplate): RedirectResponse
    {
        $this->authorize('delete', $taskTemplate);

        // No cascade: Task.task_template_id is nullOnDelete (see the tasks
        // migration), so every task this template already produced keeps its
        // title, audience and due date exactly as they were.
        $taskTemplate->delete();

        AuditLogger::record(
            event: 'task_template.deleted',
            actor: auth('staff')->user(),
            context: ['title' => $taskTemplate->title],
            subject: $taskTemplate->public_id,
        );

        return back();
    }

    /**
     * The workflow panel sends the stage it is editing; T-14's settings screen
     * has no stages and sends none. Absent means what the column defaults to
     * in the database — applied at booking, counted from the shoot's date.
     */
    private function validated(Request $request): array
    {
        $booked = app(CrmVocabulary::class)->anchored(StagePipeline::Project, StageAnchor::Booked);

        $request->merge([
            'pipeline' => $request->input('pipeline', StagePipeline::Project->value),
            'stage' => $request->input('stage', $booked?->key),
            'anchor' => $request->input('anchor', DueAnchor::ShootDate->value),
        ]);

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::enum(TaskAudience::class)],
            'project_type' => ['nullable', 'string', Rule::exists('project_types', 'key')],
            'pipeline' => ['required', Rule::enum(StagePipeline::class)],
            'stage' => ['required', 'string', new StageOfPipeline($request->input('pipeline'))],
            'day_offset' => ['required', 'integer'],
            'anchor' => ['required', Rule::enum(DueAnchor::class)],
            'shoot_kind' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
