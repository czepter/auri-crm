<?php

use App\Automation\Actions\SweepDateOffsetAutomationRules;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationCondition;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function shootIn(int $days, ?Project $project = null): Shoot
{
    $project ??= Project::factory()->create();
    // The factory's own booker would be a second person in a "solo" studio.
    Job::factory()->for($project)->create(['booked_by_id' => User::query()->value('id')]);

    return Shoot::factory()->for($project)->create([
        'date' => CarbonImmutable::parse('2026-06-01', 'UTC')->addDays($days)->toDateString(),
    ]);
}

function sweepOn(string $date = '2026-06-01'): int
{
    return app(SweepDateOffsetAutomationRules::class)(CarbonImmutable::parse($date, 'UTC'));
}

it('sends a studio rule to the crew of the shoot', function () {
    Notification::fake();

    $crewed = staffMember();
    $uninvolved = staffMember();

    $shoot = shootIn(7);
    CrewAssignment::factory()->create(['shoot_id' => $shoot->id, 'user_id' => $crewed->id]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Crew]);

    expect(sweepOn())->toBe(1);

    Notification::assertSentTo($crewed, AutomationReminder::class);
    Notification::assertNotSentTo($uninvolved, AutomationReminder::class);
});

// crew schweigt ohne Zuweisung, studio faellt auf alle zurueck.
it('keeps a crew rule silent without a crew and widens a studio rule to everyone', function () {
    Notification::fake();
    $staff = staffMember();
    staffMember();

    shootIn(7);

    $crewRule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Crew]);

    sweepOn();

    expect($crewRule->runs()->first()->status)->toBe(AutomationRunStatus::Failed);
    Notification::assertNotSentTo($staff, AutomationReminder::class);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PrimaryShoot, 7, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Studio]);

    Shoot::factory()->for(Job::factory()->create(['booked_by_id' => $staff->id])->project)->create([
        'date' => CarbonImmutable::parse('2026-06-08', 'UTC')->toDateString(),
        'is_primary' => true,
    ]);

    sweepOn();

    Notification::assertSentTo($staff, AutomationReminder::class);
});

it('resolves placeholders against the anchor’s own project', function () {
    Notification::fake();
    staffMember();

    $project = Project::factory()->create(['name' => 'Hochzeit Meier']);
    $shoot = shootIn(7, $project);
    CrewAssignment::factory()->create(['shoot_id' => $shoot->id, 'user_id' => User::query()->first()->id]);

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create([
            'recipient_type' => AutomationRecipient::Crew,
            'notification_subject' => 'Morgen: {{ project.name }}',
            'notification_body' => 'Ort: {{ project.nothing }}',
        ]);

    sweepOn();

    Notification::assertSentTo(User::query()->first(), AutomationReminder::class, function ($notification) {
        // Unbekanntes Feld bleibt stehen: eine sichtbare Luecke ist besser
        // als eine verhinderte Nachricht.
        return $notification->subject() === 'Morgen: Hochzeit Meier'
            && $notification->body() === 'Ort: {{ project.nothing }}';
    });
});

it('records a run as skipped when the condition does not hold', function () {
    Notification::fake();
    staffMember();

    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    shootIn(7, $project);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create(['condition' => AutomationCondition::PaymentOpen]);

    sweepOn();

    expect(AutomationRun::query()->first()->status)->toBe(AutomationRunStatus::Skipped);
    Notification::assertNothingSent();
});

// Primary contact whose portal access was revoked: the partner is who the studio still reaches.
it('addresses the client side the portal reaches, not the bare primary contact', function () {
    Notification::fake();
    staffMember();

    $project = Project::factory()->create(['portal_visible' => true]);
    $revoked = Contact::factory()->create(['portal_access_revoked_at' => now()]);
    $partner = Contact::factory()->create();
    $project->attachContact($revoked, ProjectContactRole::Primary);
    $project->attachContact($partner, ProjectContactRole::Partner);
    shootIn(7, $project);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Client]);

    sweepOn();

    Notification::assertSentTo($partner, AutomationReminder::class);
    Notification::assertNotSentTo($revoked, AutomationReminder::class);
});

// Wedding copy on a portrait booking is worse than silence.
it('skips a project of another type than the rule names', function () {
    Notification::fake();
    $owner = staffMember();

    shootIn(7, Project::factory()->wedding()->create());
    shootIn(7, Project::factory()->create());

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Studio, 'project_type' => 'wedding']);

    sweepOn();

    expect($rule->runs()->pluck('status')->map->value->sort()->values()->all())->toBe(['sent', 'skipped'])
        ->and(Notification::sent($owner, AutomationReminder::class))->toHaveCount(1);
});

it('sends a crew rule to the only person in a solo studio', function () {
    Notification::fake();
    $owner = staffMember();

    shootIn(7);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Crew]);

    sweepOn();

    Notification::assertSentTo($owner, AutomationReminder::class);
});
