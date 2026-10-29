<?php

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Crm\Actions\RemindDueTasks;
use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Studio\TasksDue;
use App\Notifications\Support\NotificationPreferences;
use App\Privacy\Actions\EraseContactDsgvo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates a studio task without a project and shows it on the board', function () {
    $staff = staffMember();
    $contact = Contact::factory()->create(['name' => 'Tom Brandt']);

    $this->actingAs($staff, 'staff')
        ->post('/aufgaben', [
            'title' => 'Buchhaltung abgeben',
            'due_on' => '2026-10-15',
            'assignee' => $staff->public_id,
            'contact' => $contact->public_id,
        ])
        ->assertRedirect();

    $task = Task::sole();

    expect($task->case_id)->toBeNull()
        ->and($task->contact_id)->toBe($contact->id)
        ->and($task->assignee_id)->toBe($staff->id);

    $this->actingAs($staff, 'staff')
        ->get('/aufgaben')
        ->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.projectId', null)
            ->where('tasks.0.contactName', 'Tom Brandt')
        );
});

it('refuses a client task without a project', function () {
    $this->actingAs(staffMember(), 'staff')
        ->post('/aufgaben', ['title' => 'Fragebogen ausfüllen', 'audience' => 'client'])
        ->assertInvalid('audience');

    expect(Task::count())->toBe(0);
});

it('completes a studio task', function () {
    $staff = staffMember();
    $task = Task::factory()->create(['case_id' => null]);

    $this->actingAs($staff, 'staff')->post("/aufgaben/{$task->public_id}/erledigt")->assertRedirect();

    expect($task->fresh()->isDone())->toBeTrue();
});

it('edits a task and keeps its anchor until the day changes', function () {
    $staff = staffMember();
    $task = Task::factory()->create(['case_id' => null, 'title' => 'Alt', 'due_on' => '2026-10-15', 'due_anchor' => 'x', 'due_offset_days' => 2]);

    $this->actingAs($staff, 'staff')
        ->patch("/aufgaben/{$task->public_id}", ['title' => 'Neu', 'due_on' => '2026-10-15'])
        ->assertRedirect();

    expect($task->fresh())->title->toBe('Neu')->due_anchor->toBe('x');

    $this->actingAs($staff, 'staff')
        ->patch("/aufgaben/{$task->public_id}", ['title' => 'Neu', 'due_on' => '2026-10-20']);

    expect($task->fresh()->due_on->toDateString())->toBe('2026-10-20')
        ->and($task->fresh()->due_anchor)->toBeNull();
});

it('lets two studio tasks depend on each other', function () {
    $first = Task::factory()->create(['case_id' => null, 'title' => 'Belege sammeln']);
    $second = Task::factory()->create(['case_id' => null]);

    $this->actingAs(staffMember(), 'staff')
        ->post("/aufgaben/{$second->public_id}/abhaengigkeit", ['depends_on' => $first->public_id])
        ->assertRedirect();

    expect($second->fresh()->isBlocked())->toBeTrue();
});

it('names a due studio task by its contact, or by its title alone', function () {
    Notification::fake();
    $staff = staffMember();
    app(NotificationPreferences::class)->setMode($staff, TasksDue::class, NotificationMode::Immediate);
    $contact = Contact::factory()->create(['name' => 'Tom Brandt']);

    Task::factory()->due(now()->toDateString())->create(['case_id' => null, 'title' => 'Auskunft geben', 'contact_id' => $contact->id]);
    Task::factory()->due(now()->toDateString())->create(['case_id' => null, 'title' => 'Steuer melden']);

    app(RemindDueTasks::class)();

    Notification::assertSentTo($staff, TasksDue::class, fn (TasksDue $notification) => $notification->lines() === [
        'Tom Brandt — Auskunft geben, heute fällig',
        'Steuer melden, heute fällig',
    ]);
});

it('files an automation without a project as a studio task', function () {
    staffMember();
    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 0, AutomationOffsetDirection::After)
        ->create(['recipient_type' => AutomationRecipient::Studio, 'action' => AutomationAction::Task, 'notification_subject' => 'Buchhaltung']);

    $run = app(FireAutomationRule::class)($rule, Contact::factory()->create(), '2026-06-01');

    expect($run->status)->toBe(AutomationRunStatus::Sent)
        ->and(Task::sole())->title->toBe('Buchhaltung')->case_id->toBeNull();
});

it('keeps a studio task when its contact is erased', function () {
    $staff = staffMember();
    $contact = Contact::factory()->create();
    $task = Task::factory()->create(['case_id' => null, 'contact_id' => $contact->id]);

    app(EraseContactDsgvo::class)($contact, $staff);

    expect($task->fresh()->contact_id)->toBeNull();
});
