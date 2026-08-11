<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Data\TaskTemplateData;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\TaskTemplate;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
    public function index(Request $request, ApplyTaskTemplates $apply): Response
    {
        $this->authorize('viewAny', TaskTemplate::class);

        $validated = $request->validate([
            'preview_date' => ['nullable', 'date'],
            'preview_shoot_kind' => ['nullable', Rule::enum(ShootKind::class)],
        ]);

        $templates = TaskTemplate::query()->orderBy('sort_order')->get();

        return Inertia::render('Settings/TaskTemplates', [
            'templates' => TaskTemplateData::collect($templates),
            'audiences' => collect(TaskAudience::cases())
                ->map(fn (TaskAudience $audience) => ['value' => $audience->value, 'label' => $audience->label()])
                ->all(),
            'shootKinds' => collect(ShootKind::cases())
                ->map(fn (ShootKind $kind) => ['value' => $kind->value, 'label' => $kind->label()])
                ->all(),
            'previewDate' => $validated['preview_date'] ?? null,
            'previewShootKind' => $validated['preview_shoot_kind'] ?? null,
            'preview' => isset($validated['preview_date'])
                ? $apply->preview(
                    CarbonImmutable::parse($validated['preview_date']),
                    isset($validated['preview_shoot_kind']) ? ShootKind::from($validated['preview_shoot_kind']) : null,
                )
                : null,
        ]);
    }

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

        return redirect('/einstellungen/aufgabenvorlagen');
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

        return redirect('/einstellungen/aufgabenvorlagen');
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

        return redirect('/einstellungen/aufgabenvorlagen');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::enum(TaskAudience::class)],
            'day_offset' => ['required', 'integer'],
            'shoot_kind' => ['nullable', Rule::enum(ShootKind::class)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
