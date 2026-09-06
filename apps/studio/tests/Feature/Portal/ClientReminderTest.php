<?php

use App\Crm\Actions\RemindDueTasks;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Portal\Notifications\PaymentReminder;
use App\Portal\Notifications\TaskDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function projectWithClient(): array
{
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    return [$project, $contact];
}

function planFor(Project $project, bool $remindClients = true): PaymentPlan
{
    return PaymentPlan::factory()->create([
        'job_id' => Job::factory()->create(['project_id' => $project->id]),
        'client_reminders_enabled' => $remindClients,
    ]);
}

// Der Kundenweg beginnt auf der zweiten Sprosse: ein Tag Verspaetung ist eine
// Ueberweisung, die unterwegs ist.
it('holds the payment reminder until the second rung', function () {
    Notification::fake();
    staffMember();

    [$project, $contact] = projectWithClient();

    $installment = PaymentInstallment::factory()->create([
        'payment_plan_id' => planFor($project),
        'due_on' => now()->subDay()->toDateString(),
    ]);

    app(DetectOverdueInstallments::class)();
    Notification::assertNotSentTo($contact, PaymentReminder::class);

    $this->travel(4)->days();
    app(DetectOverdueInstallments::class)();

    Notification::assertSentTo($contact, PaymentReminder::class);
    expect($installment->fresh()->client_reminders_sent)->toBe(2);
});

it('says nothing to a client whose plan has reminders switched off', function () {
    Notification::fake();
    staffMember();

    [$project, $contact] = projectWithClient();

    PaymentInstallment::factory()->create([
        'payment_plan_id' => planFor($project, remindClients: false),
        'due_on' => now()->subDays(5)->toDateString(),
    ]);

    app(DetectOverdueInstallments::class)();

    Notification::assertNotSentTo($contact, PaymentReminder::class);
});

it('reminds the client of their own open task on the second rung', function () {
    Notification::fake();
    staffMember();

    [$project, $contact] = projectWithClient();

    Task::factory()->due(now()->toDateString())->create([
        'project_id' => $project->id,
        'audience' => TaskAudience::Client,
    ]);

    app(RemindDueTasks::class)();
    Notification::assertNotSentTo($contact, TaskDue::class);

    $this->travel(3)->days();
    app(RemindDueTasks::class)();

    Notification::assertSentTo($contact, TaskDue::class);
});

it('says nothing where no contact can reach the portal', function () {
    Notification::fake();
    staffMember();

    $project = Project::factory()->create(['portal_visible' => false]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    Task::factory()->due(now()->subDays(4)->toDateString())->create([
        'project_id' => $project->id,
        'audience' => TaskAudience::Client,
    ]);

    app(RemindDueTasks::class)();

    Notification::assertNotSentTo($contact, TaskDue::class);
});
