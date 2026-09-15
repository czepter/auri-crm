<?php

use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Events\ProjectBooked;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('fires an event-triggered rule when its named domain event dispatches, anchored to the project the event carries', function () {
    Notification::fake();

    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();

    $rule = AutomationRule::factory()->event(ProjectBooked::class)->create();

    event(new ProjectBooked($project, $job));

    $run = AutomationRun::where('automation_rule_id', $rule->id)->sole();

    expect($run->anchorable_type)->toBe(Project::class)
        ->and($run->anchorable_id)->toBe($project->id)
        ->and($run->status)->toBe(AutomationRunStatus::Sent);

    Notification::assertSentTo($contact, AutomationReminder::class);
});

it('never fires a rule for an event class it does not name', function () {
    Notification::fake();

    $project = Project::factory()->create();
    $installment = PaymentInstallment::factory()->create();

    // Watches ProjectBooked, not InstallmentPaid.
    AutomationRule::factory()->event(ProjectBooked::class)->create();

    event(new InstallmentPaid($installment));

    // No run is the whole assertion: a reminder only ever leaves through one.
    // T-11 now tells the studio about a paid installment off this same event,
    // so "nothing at all was sent" stopped being the question.
    expect(AutomationRun::count())->toBe(0);
});

it('never re-fires an event-triggered rule for the same anchor if the event somehow dispatches twice in one day', function () {
    Notification::fake();

    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();

    AutomationRule::factory()->event(ProjectBooked::class)->create();

    event(new ProjectBooked($project, $job));
    event(new ProjectBooked($project, $job));

    expect(AutomationRun::count())->toBe(1);
});

it('never fires an inactive event-triggered rule', function () {
    Notification::fake();

    $project = Project::factory()->create();
    $job = Job::factory()->for($project)->create();

    AutomationRule::factory()->event(ProjectBooked::class)->inactive()->create();

    event(new ProjectBooked($project, $job));

    expect(AutomationRun::count())->toBe(0);
});
