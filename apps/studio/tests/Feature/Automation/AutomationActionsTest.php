<?php

use App\Automation\Actions\SweepDateOffsetAutomationRules;
use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function bookedShootIn(int $days, Project $project): Shoot
{
    Job::factory()->for($project)->create();

    return Shoot::factory()->for($project)->create([
        'date' => CarbonImmutable::parse('2026-06-01')->addDays($days)->toDateString(),
    ]);
}

it('hands the studio a task due on the anchor date less the offset', function () {
    staffMember();
    $project = Project::factory()->create(['name' => 'Hochzeit Meier']);
    $shoot = bookedShootIn(3, $project);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 3, AutomationOffsetDirection::Before)
        ->create([
            'recipient_type' => AutomationRecipient::Studio,
            'action' => AutomationAction::Task,
            'notification_subject' => 'Packliste: {{ project.name }}',
        ]);

    app(SweepDateOffsetAutomationRules::class)(CarbonImmutable::parse('2026-06-01'));

    $task = Task::sole();

    expect($task->title)->toBe('Packliste: Hochzeit Meier')
        ->and($task->audience)->toBe(TaskAudience::Internal)
        ->and($task->project_id)->toBe($project->id)
        ->and($task->shoot_id)->toBe($shoot->id)
        ->and($task->due_on->toDateString())->toBe('2026-06-01')
        ->and(AutomationRun::sole()->status)->toBe(AutomationRunStatus::Sent);

    Notification::assertNothingSent();
});

it('drafts a client mail with its rendered text instead of sending it', function () {
    $project = Project::factory()->create(['name' => 'Hochzeit Meier', 'portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    bookedShootIn(7, $project);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create([
            'recipient_type' => AutomationRecipient::Client,
            'notification_subject' => 'Bald: {{ project.name }}',
            'notification_body' => 'Hallo {{ contact.name }}',
        ]);

    app(SweepDateOffsetAutomationRules::class)(CarbonImmutable::parse('2026-06-01'));

    expect(AutomationRun::sole())
        ->status->toBe(AutomationRunStatus::AwaitingApproval)
        ->subject->toBe('Bald: Hochzeit Meier')
        ->body->toBe("Hallo {$contact->name}")
        ->contact_ids->toBe([$contact->id]);

    Notification::assertNothingSent();
});

// Wer eine Regel an den Kunden umstellt, bekommt keinen Direktversand zurück.
it('keeps a client rule a draft whatever action it was saved with', function () {
    $rule = AutomationRule::factory()->create([
        'recipient_type' => AutomationRecipient::Client,
        'action' => AutomationAction::Notice,
    ]);

    expect($rule->fresh()->action)->toBe(AutomationAction::MailDraft);

    $rule->update(['recipient_type' => AutomationRecipient::Studio]);

    expect($rule->fresh()->action)->toBe(AutomationAction::Notice);
});
