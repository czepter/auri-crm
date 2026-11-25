<?php

use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Notifications\PaymentReminder;
use App\Portal\Actions\RemindClientTasks;
use App\Portal\Notifications\TaskDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function projectWithClient(): array
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    return [$project, $contact];
}

function planFor(ClientCase $project): PaymentPlan
{
    return PaymentPlan::factory()->create([
        'case_id' => markBooked($project),
    ]);
}

// Der Kundenweg beginnt auf der zweiten Sprosse: ein Tag Verspaetung ist eine
// Ueberweisung, die unterwegs ist. Er endet im Entwurf, nie im Versand.
it('files the payment reminder as a draft on the second rung', function () {
    Notification::fake();
    Mail::fake();
    staffMember();

    [$project, $contact] = projectWithClient();

    $installment = PaymentInstallment::factory()->create([
        'payment_plan_id' => planFor($project),
        'due_on' => now()->subDay()->toDateString(),
    ]);

    $this->artisan('payments:detect-overdue-installments');
    expect(AutomationRun::count())->toBe(0);

    $this->travel(4)->days();
    $this->artisan('payments:detect-overdue-installments');
    $this->artisan('payments:detect-overdue-installments');

    $draft = AutomationRun::sole();
    expect($draft->status)->toBe(AutomationRunStatus::AwaitingApproval)
        ->and($draft->contact_ids)->toBe([$contact->id])
        ->and($draft->anchorable->is($installment))->toBeTrue()
        ->and($installment->fresh()->client_reminders_sent)->toBe(2);

    Notification::assertNotSentTo($contact, AutomationReminder::class);
    Mail::assertNothingSent();
});

// #414: eine angezahlte Rate wird über den Rest angemahnt, nicht über den
// Ratenbetrag.
it('names the remaining amount in the payment reminder', function () {
    staffMember();

    [$project, $contact] = projectWithClient();

    $installment = PaymentInstallment::factory()->create([
        'payment_plan_id' => planFor($project),
        'amount' => 50000,
        'paid_amount' => 20000,
        'status' => PaymentInstallmentStatus::Partial,
        'due_on' => now()->subDays(5)->toDateString(),
    ]);

    expect((new PaymentReminder($installment))->draft($contact)['body'])->toContain('300,00');
});

it('reminds the client of their own open task on the second rung', function () {
    Notification::fake();
    staffMember();

    [$project, $contact] = projectWithClient();

    Task::factory()->due(now()->toDateString())->create([
        'case_id' => $project->id,
        'audience' => TaskAudience::Client,
    ]);

    app(RemindClientTasks::class)();
    Notification::assertNotSentTo($contact, TaskDue::class);

    $this->travel(3)->days();
    app(RemindClientTasks::class)();

    Notification::assertSentTo($contact, TaskDue::class);
});

it('says nothing where no contact can reach the portal', function () {
    Notification::fake();
    staffMember();

    $project = ClientCase::factory()->create(['portal_visible' => false]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    Task::factory()->due(now()->subDays(4)->toDateString())->create([
        'case_id' => $project->id,
        'audience' => TaskAudience::Client,
    ]);

    app(RemindClientTasks::class)();

    Notification::assertNotSentTo($contact, TaskDue::class);
});
