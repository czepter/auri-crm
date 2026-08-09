<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\ProjectPositionData;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function positionOf(Project $project): ProjectPositionData
{
    return app(DeriveProjectPosition::class)($project);
}

it('reads the least advanced shoot, not the furthest', function () {
    // A delivered engagement session beside a booked wedding reads "Gebucht":
    // the honest answer to what this project still owes the client.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Delivered)->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Booked)->create();

    expect(positionOf($project)->value)->toBe('booked')
        ->and(positionOf($project)->label)->toBe('Gebucht')
        ->and(positionOf($project)->source)->toBe('shoot');
});

it('changes when the underlying shoot status changes, with no field to keep in sync', function () {
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->atStatus(ShootStatus::Booked)->create();

    expect(positionOf($project)->value)->toBe('booked');

    app(AdvanceShootStatus::class)($shoot, ShootStatus::Shot);

    expect(positionOf($project)->value)->toBe('shot');
});

it('does not let a cancelled shoot hold the project back', function () {
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->cancelled('Abgesagt')->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Editing)->create();

    expect(positionOf($project)->value)->toBe('editing');
});

it('falls back to the lead stage when no uncancelled shoot exists', function () {
    $project = Project::factory()->create();
    Lead::factory()->for($project)->atStage(LeadStage::QuoteSent)->create();
    Shoot::factory()->for($project)->cancelled('Abgesagt')->create();

    expect(positionOf($project)->source)->toBe('lead')
        ->and(positionOf($project)->value)->toBe('quote_sent')
        ->and(positionOf($project)->label)->toBe('Angebot');
});

it('changes when the underlying lead stage changes', function () {
    $project = Project::factory()->create();
    $lead = Lead::factory()->for($project)->atStage(LeadStage::New)->create();

    expect(positionOf($project)->value)->toBe('new');

    $lead->update(['stage' => LeadStage::Contacted]);

    expect(positionOf($project->fresh())->value)->toBe('contacted');
});

it('reads lost when the lead is lost and nothing is shooting', function () {
    $project = Project::factory()->create();
    Lead::factory()->for($project)->lost('Budget')->create();

    expect(positionOf($project)->source)->toBe('lost')
        ->and(positionOf($project)->label)->toBe('Verloren');
});

it('answers for a project with neither shoots nor a lead', function () {
    expect(positionOf(Project::factory()->create())->source)->toBe('none');
});

it('exposes the rule over an already loaded collection, for the board to reuse', function () {
    // P-04 feeds it one project's shoots out of a single board query rather
    // than deriving the same comparison a second way in SQL.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Ready)->create();
    $earliest = Shoot::factory()->for($project)->atStatus(ShootStatus::Prep)->create();
    Shoot::factory()->for($project)->cancelled('Abgesagt')->create();

    $least = app(DeriveProjectPosition::class)->leastAdvanced($project->shoots);

    expect($least->is($earliest))->toBeTrue();
});
