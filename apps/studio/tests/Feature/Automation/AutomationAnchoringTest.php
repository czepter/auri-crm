<?php

use App\Automation\Actions\SweepDateOffsetAutomationRules;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('fires a per-shoot rule once per matching shoot, anchored to that shoot', function () {
    Notification::fake();

    $today = CarbonImmutable::parse('2026-06-01', 'UTC');

    $project = Project::factory()->wedding()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    markBooked($project);

    // Due in 7 days — matches the rule below.
    $engagement = Shoot::factory()->for($project)->create([
        'kind' => 'Verlobungsshooting',
        'date' => $today->addDays(7)->toDateString(),
        'is_primary' => false,
    ]);

    // Due in 20 days — does not match.
    Shoot::factory()->for($project)->create([
        'kind' => 'Hochzeitstag',
        'date' => $today->addDays(20)->toDateString(),
        'is_primary' => true,
    ]);

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    $fired = app(SweepDateOffsetAutomationRules::class)($today);

    expect($fired)->toBe(1);

    $run = AutomationRun::where('automation_rule_id', $rule->id)->sole();

    expect($run->anchorable_type)->toBe(Shoot::class)
        ->and($run->anchorable_id)->toBe($engagement->id)
        ->and($run->status)->toBe(AutomationRunStatus::AwaitingApproval);
});

it('fires a primary-shoot rule anchored to the project, not the shoot, on a multi-shoot project', function () {
    Notification::fake();

    $today = CarbonImmutable::parse('2026-06-01', 'UTC');

    $project = Project::factory()->wedding()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    markBooked($project);

    // The engagement shoot sits 7 days out — a per_shoot rule would catch
    // it, but this rule only watches the primary shoot, 20 days out.
    Shoot::factory()->for($project)->create([
        'kind' => 'Verlobungsshooting',
        'date' => $today->addDays(7)->toDateString(),
        'is_primary' => false,
    ]);

    $wedding = Shoot::factory()->for($project)->create([
        'kind' => 'Hochzeitstag',
        'date' => $today->addDays(20)->toDateString(),
        'is_primary' => true,
    ]);

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PrimaryShoot, 20, AutomationOffsetDirection::Before)
        ->create();

    $fired = app(SweepDateOffsetAutomationRules::class)($today);

    expect($fired)->toBe(1);

    $run = AutomationRun::where('automation_rule_id', $rule->id)->sole();

    expect($run->anchorable_type)->toBe(Project::class)
        ->and($run->anchorable_id)->toBe($project->id)
        ->and($wedding->is_primary)->toBeTrue();
});

it('fires an "after" rule when today is offset_days past the anchor date', function () {
    Notification::fake();

    $today = CarbonImmutable::parse('2026-06-10', 'UTC');

    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    markBooked($project);

    $shoot = Shoot::factory()->for($project)->create([
        'date' => $today->subDays(3)->toDateString(),
    ]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 3, AutomationOffsetDirection::After)
        ->create();

    $fired = app(SweepDateOffsetAutomationRules::class)($today);

    expect($fired)->toBe(1);
    expect(AutomationRun::sole()->anchorable_id)->toBe($shoot->id);
});

it('never fires an inactive rule', function () {
    $today = CarbonImmutable::parse('2026-06-01', 'UTC');
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create(['date' => $today->addDays(7)->toDateString()]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->inactive()
        ->create();

    expect(app(SweepDateOffsetAutomationRules::class)($today))->toBe(0);
    expect(AutomationRun::count())->toBe(0);
});

// An inquiry that never booked has no shooting to remind anyone of.
it('never fires against a shoot on an unbooked project', function () {
    $today = CarbonImmutable::parse('2026-06-01', 'UTC');
    Shoot::factory()->primary()->create(['date' => $today->addDays(7)->toDateString()]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();
    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PrimaryShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    expect(app(SweepDateOffsetAutomationRules::class)($today))->toBe(0);
});

it('never fires against a cancelled shoot', function () {
    $today = CarbonImmutable::parse('2026-06-01', 'UTC');
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->cancelled('client withdrew')->create([
        'date' => $today->addDays(7)->toDateString(),
    ]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    expect(app(SweepDateOffsetAutomationRules::class)($today))->toBe(0);
});

it('still writes exactly one AutomationRun, marked failed, when the anchor has no primary contact to notify', function () {
    Notification::fake();

    $today = CarbonImmutable::parse('2026-06-01', 'UTC');
    $project = Project::factory()->create(); // no contact attached
    markBooked($project);
    Shoot::factory()->for($project)->create(['date' => $today->addDays(7)->toDateString()]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    app(SweepDateOffsetAutomationRules::class)($today);

    $run = AutomationRun::sole();
    expect($run->status)->toBe(AutomationRunStatus::Failed);

    Notification::assertNothingSent();
});

it('fires a quote-validity rule against the quote project', function () {
    // The anchor is the Project, not the Quote: a revision is a new Quote row
    // (Q-06), and a nudge trail that resets when the studio revises an offer
    // would report "never reminded" about a client reminded twice.
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(7)->toDateString(),
    ]);

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::QuoteValidity, 7, AutomationOffsetDirection::Before)
        ->create(['key' => 'quote-follow-up']);

    $fired = app(SweepDateOffsetAutomationRules::class)();

    expect($fired)->toBe(1)
        ->and(AutomationRun::query()->where('automation_rule_id', $rule->id)->first())
        ->anchorable_type->toBe($project->getMorphClass())
        ->anchorable_id->toBe($project->id);
});

it('does not fire a quote-validity rule for a quote that is not sent', function () {
    // A draft has not been put in front of anyone; there is nothing to chase.
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Draft,
        'valid_until' => now()->addDays(7)->toDateString(),
    ]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::QuoteValidity, 7, AutomationOffsetDirection::Before)
        ->create(['key' => 'quote-follow-up']);

    expect(app(SweepDateOffsetAutomationRules::class)())->toBe(0);
});
