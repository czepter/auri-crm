<?php

use App\Crm\Actions\RemindDueTasks;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Task;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Studio\TasksDue;
use App\Notifications\Support\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Auf sofort gestellt, weil die Vorgabe der Tagesueberblick ist und der die
// Mail bewusst zurueckhaelt (T-23).
it('reminds on the due day itself', function () {
    Notification::fake();
    $staff = staffMember();
    app(NotificationPreferences::class)->setMode($staff, TasksDue::class, NotificationMode::Immediate);

    Task::factory()->due(now()->toDateString())->create(['assignee_id' => $staff->id]);

    expect(app(RemindDueTasks::class)())->toBe(1);

    Notification::assertSentTo($staff, TasksDue::class);
});

it('says nothing about a task not yet due', function () {
    Notification::fake();
    staffMember();

    Task::factory()->due(now()->addDay()->toDateString())->create();

    expect(app(RemindDueTasks::class)())->toBe(0);
});

// Drei Wochen überfällig meldet sich in der dritten Woche einmal.
it('climbs one rung at a time', function () {
    Notification::fake();
    staffMember();

    $task = Task::factory()->due(now()->toDateString())->create();

    app(RemindDueTasks::class)();
    expect($task->fresh()->reminders_sent)->toBe(1)
        ->and(app(RemindDueTasks::class)())->toBe(0);

    $this->travel(3)->days();

    expect(app(RemindDueTasks::class)())->toBe(1)
        ->and($task->fresh()->reminders_sent)->toBe(2);
});

it('leaves out tasks without a due date, client tasks and blocked ones', function () {
    Notification::fake();
    staffMember();

    Task::factory()->due(null)->create();
    Task::factory()->due(now()->toDateString())->create(['audience' => TaskAudience::Client]);

    $blocker = Task::factory()->due(now()->toDateString())->create();
    Task::factory()->due(now()->toDateString())->create([
        'project_id' => $blocker->project_id,
        'depends_on_task_id' => $blocker->id,
    ]);

    app(RemindDueTasks::class)();

    expect(Task::query()->where('reminders_sent', '>', 0)->pluck('id')->all())->toBe([$blocker->id]);
});

it('sends to the assignee alone where a task names one', function () {
    Notification::fake();
    $assignee = staffMember();
    $other = staffMember();

    foreach ([$assignee, $other] as $user) {
        app(NotificationPreferences::class)->setMode($user, TasksDue::class, NotificationMode::Immediate);
    }

    Task::factory()->due(now()->toDateString())->create(['assignee_id' => $assignee->id]);

    app(RemindDueTasks::class)();

    Notification::assertSentTo($assignee, TasksDue::class);
    Notification::assertNotSentTo($other, TasksDue::class);
});
