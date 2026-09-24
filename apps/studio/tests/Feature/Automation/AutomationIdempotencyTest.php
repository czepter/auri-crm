<?php

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Actions\SweepDateOffsetAutomationRules;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * The acceptance criterion, proven directly: a rule firing writes exactly
 * one AutomationRun, and re-processing the same trigger produces no second
 * one — the "redeployed scheduler" scenario E-08 exists to guard against.
 */
it('fires exactly one AutomationRun when the same rule/anchor/date is processed twice through FireAutomationRule', function () {
    Notification::fake();

    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $shoot = Shoot::factory()->for($project)->create();

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    $first = app(FireAutomationRule::class)($rule, $shoot, '2026-06-08');
    $second = app(FireAutomationRule::class)($rule, $shoot, '2026-06-08');

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull();

    expect(AutomationRun::count())->toBe(1)
        ->and(AutomationRun::sole()->contact_ids)->toBe([$contact->id]);
});

it('never re-fires when the daily sweep is re-run for the same day, as a redeployed scheduler would', function () {
    Notification::fake();

    $today = CarbonImmutable::parse('2026-06-01', 'UTC');
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    markBooked($project);
    Shoot::factory()->for($project)->create(['date' => $today->addDays(7)->toDateString()]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    $firstRun = app(SweepDateOffsetAutomationRules::class)($today);
    $secondRun = app(SweepDateOffsetAutomationRules::class)($today);

    expect($firstRun)->toBe(1)
        ->and($secondRun)->toBe(0);

    expect(AutomationRun::count())->toBe(1);
});

it('fires a fresh AutomationRun on a different trigger date for the same rule and anchor', function () {
    Notification::fake();

    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $shoot = Shoot::factory()->for($project)->create();

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    app(FireAutomationRule::class)($rule, $shoot, '2026-06-08');
    app(FireAutomationRule::class)($rule, $shoot, '2026-06-09');

    expect(AutomationRun::count())->toBe(2);
});
