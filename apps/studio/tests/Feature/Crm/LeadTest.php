<?php

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts at the new stage and records when it got there', function () {
    $lead = Lead::factory()->create();

    expect($lead->stage->key)->toBe(LeadStage::New->value)
        ->and($lead->stage_entered_at)->not->toBeNull()
        ->and($lead->isLost())->toBeFalse();
});

it('allows only one lead per project', function () {
    $project = Project::factory()->create();

    Lead::factory()->for($project)->create();
    Lead::factory()->for($project)->create();
})->throws(QueryException::class);

it('knows the funnel order', function () {
    expect(LeadStage::New->position())->toBe(0)
        ->and(LeadStage::Booked->position())->toBe(5)
        ->and(LeadStage::New->next())->toBe(LeadStage::Contacted)
        ->and(LeadStage::Booked->next())->toBeNull();
});

it('permits transitions in both directions and refuses only the no-op', function () {
    expect(LeadStage::New->canTransitionTo(LeadStage::Contacted))->toBeTrue()
        // Skipping forward is legitimate: a client accepts on the phone before
        // any formal quote existed, and forcing staff through the intermediate
        // stages would make the funnel a fiction.
        ->and(LeadStage::New->canTransitionTo(LeadStage::Booked))->toBeTrue()
        // Backwards is how a mis-drop on the board is undone. Leaving the wrong
        // stage standing is the worse record.
        ->and(LeadStage::QuoteSent->canTransitionTo(LeadStage::Contacted))->toBeTrue()
        ->and(LeadStage::Booked->canTransitionTo(LeadStage::New))->toBeTrue()
        ->and(LeadStage::Contacted->canTransitionTo(LeadStage::Contacted))->toBeFalse();
});

it('keeps the stage a lead was lost in', function () {
    // Which stage a lead died in is the number the funnel is measured on, so
    // lost is a timestamp beside the stage rather than a seventh stage.
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->lost('Budget')->create();

    expect($lead->isLost())->toBeTrue()
        ->and($lead->lost_reason)->toBe('Budget')
        ->and($lead->stage->key)->toBe(LeadStage::QuoteSent->value);
});

it('excludes lost leads from the open scope', function () {
    Lead::factory()->count(2)->create();
    Lead::factory()->lost()->create();

    expect(Lead::query()->open()->count())->toBe(2)
        ->and(Lead::count())->toBe(3);
});

it('labels its enums in german', function () {
    expect(LeadStage::QuoteAccepted->label())->toBe('Angebot angenommen')
        ->and(LeadSource::WeddingFair->label())->toBe('Hochzeitsmesse')
        ->and(CaptureChannel::Phone->label())->toBe('Telefonisch');
});
