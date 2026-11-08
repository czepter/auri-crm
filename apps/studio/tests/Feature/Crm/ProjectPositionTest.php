<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\DeriveCasePosition;
use App\Crm\Data\CasePositionData;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function positionOf(ClientCase $project): CasePositionData
{
    return app(DeriveCasePosition::class)($project);
}

it('lets the funnel speak until the project is booked', function () {
    // Intake creates the primary shoot at `requested`, and it stays there for
    // the project's whole pre-booking life. Reading the shoots here would make
    // every unbooked project say "Angefragt" no matter how far the funnel got —
    // and contradict the board, which reads the stage directly.
    $project = ClientCase::factory()->create();
    Lead::factory()->for($project, 'case')->atStage(LeadStage::QuoteAccepted)->create();
    Shoot::factory()->for($project, 'case')->primary()->create();

    expect(positionOf($project)->source)->toBe('lead')
        ->and(positionOf($project)->value)->toBe('quote_accepted')
        ->and(positionOf($project)->label)->toBe('Angebot angenommen');
});

it('hands over to the shoots once the project is booked', function () {
    $project = ClientCase::factory()->create();
    Lead::factory()->for($project, 'case')->atStage(LeadStage::Booked)->create();
    Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Editing)->create();
    markBooked($project);

    expect(positionOf($project)->source)->toBe('shoot')
        ->and(positionOf($project)->value)->toBe('editing');
});

it('reads lost before anything else, booked or not', function () {
    $project = ClientCase::factory()->create();
    Lead::factory()->for($project, 'case')->lost('Budget')->create();
    $project->refresh();
    Shoot::factory()->for($project, 'case')->primary()->create();

    expect(positionOf($project)->source)->toBe('lost');
});

it('reads the least advanced shoot, not the furthest', function () {
    // A delivered engagement session beside a booked wedding reads "Gebucht":
    // the honest answer to what this project still owes the client.
    // Booked, because shoots only speak for a project in production.
    $project = ClientCase::factory()->booked()->create();
    Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Delivered)->create();
    Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Booked)->create();

    expect(positionOf($project)->value)->toBe('booked')
        ->and(positionOf($project)->label)->toBe('Gebucht')
        ->and(positionOf($project)->source)->toBe('shoot');
});

it('changes when the underlying shoot status changes, with no field to keep in sync', function () {
    $project = ClientCase::factory()->booked()->create();
    $shoot = Shoot::factory()->for($project, 'case')->on(now()->subDay()->toDateString())->atStatus(ShootStatus::Booked)->create();

    expect(positionOf($project)->value)->toBe('booked');

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Shot));

    expect(positionOf($project)->value)->toBe('shot');
});

it('does not let a cancelled shoot hold the project back', function () {
    $project = ClientCase::factory()->booked()->create();
    Shoot::factory()->for($project, 'case')->cancelled('Abgesagt')->create();
    Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Editing)->create();

    expect(positionOf($project)->value)->toBe('editing');
});

it('falls back to the lead stage when no uncancelled shoot exists', function () {
    $project = ClientCase::factory()->create();
    Lead::factory()->for($project, 'case')->atStage(LeadStage::QuoteSent)->create();
    Shoot::factory()->for($project, 'case')->cancelled('Abgesagt')->create();

    expect(positionOf($project)->source)->toBe('lead')
        ->and(positionOf($project)->value)->toBe('quote_sent')
        ->and(positionOf($project)->label)->toBe('Angebot');
});

it('changes when the underlying lead stage changes', function () {
    $project = ClientCase::factory()->create();
    $lead = Lead::factory()->for($project, 'case')->atStage(LeadStage::New)->create();

    expect(positionOf($project)->value)->toBe('new');

    $lead->update(['stage' => LeadStage::Contacted]);

    expect(positionOf($project->fresh())->value)->toBe('contacted');
});

it('reads lost when the lead is lost and nothing is shooting', function () {
    $project = ClientCase::factory()->create();
    Lead::factory()->for($project, 'case')->lost('Budget')->create();
    $project->refresh();

    expect(positionOf($project)->source)->toBe('lost')
        ->and(positionOf($project)->label)->toBe('Verloren');
});

it('answers for a project with neither shoots nor a lead', function () {
    expect(positionOf(ClientCase::factory()->create())->source)->toBe('none');
});

it('exposes the rule over an already loaded collection, for the board to reuse', function () {
    // P-04 feeds it one project's shoots out of a single board query rather
    // than deriving the same comparison a second way in SQL.
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Ready)->create();
    $earliest = Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Prep)->create();
    Shoot::factory()->for($project, 'case')->cancelled('Abgesagt')->create();

    $least = app(DeriveCasePosition::class)->leastAdvanced($project->shoots);

    expect($least->is($earliest))->toBeTrue();
});
