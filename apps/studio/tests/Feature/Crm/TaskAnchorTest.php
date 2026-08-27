<?php

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\TaskAnchors;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves a stage anchor to the day the lead entered it, plus the offset', function () {
    $project = Project::factory()->create();
    Lead::factory()->for($project)->create([
        'stage' => LeadStage::ContractSent,
        'stage_entered_at' => '2026-08-03 09:00:00',
    ]);

    expect(app(TaskAnchors::class)->dueOn($project, 'stage:contract_sent', 10)?->format('Y-m-d'))
        ->toBe('2026-08-13');
});

it('reads the day a stage was entered from the ledger, not from where the lead stands now', function () {
    $project = Project::factory()->create();
    $lead = Lead::factory()->for($project)->create(['stage' => LeadStage::QuoteSent]);

    $this->travelTo('2026-08-03 09:00:00');
    app(AdvanceLeadStage::class)($lead, LeadStage::ContractSent, staffMember(StaffRole::Owner));
    $this->travelBack();

    app(AdvanceLeadStage::class)($lead, LeadStage::Booked, staffMember(StaffRole::Owner));

    expect(app(TaskAnchors::class)->dueOn($project->fresh(), 'stage:contract_sent', 0)?->format('Y-m-d'))
        ->toBe('2026-08-03');
});

it('leaves a task on an anchor the project has not reached without a due date', function () {
    $project = Project::factory()->create();
    Lead::factory()->for($project)->create(['stage' => LeadStage::QuoteSent]);

    $task = $project->tasks()->create([
        'title' => 'Vorschau freigeben',
        'audience' => 'internal',
        'due_anchor' => 'stage:booked',
        'due_offset_days' => 2,
    ]);

    $row = TaskRowData::fromTask($task->fresh()->load('project'));

    expect(app(TaskAnchors::class)->dueOn($project, 'stage:booked', 2))->toBeNull()
        ->and($row->dueOn)->toBeNull()
        // Owed, but not owed on any day: it stays open and never colours late.
        ->and($row->isOverdue)->toBeFalse()
        ->and($row->dueAnchorLabel)->toBe('Gebucht');
});

it('offers the stages ahead of the lead and drops the ones it skipped', function () {
    $project = Project::factory()->create();
    Lead::factory()->for($project)->create(['stage' => LeadStage::QuoteSent]);

    $values = array_column(app(TaskAnchors::class)->forProject($project), 'value');

    expect($values)->toContain('stage:quote_sent', 'stage:booked')
        ->not->toContain('stage:contacted');
});

it('moves an anchored task when the shoot it hangs off moves', function () {
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on('2026-08-28')->create();

    $task = $project->tasks()->create([
        'title' => 'Crew-Briefing verschicken',
        'audience' => 'internal',
        'due_on' => '2026-08-27',
        'due_anchor' => 'shoot:'.$shoot->public_id,
        'due_offset_days' => -1,
    ]);

    $shoot->update(['date' => '2026-09-04']);

    expect($task->fresh()->due_on->format('Y-m-d'))->toBe('2026-09-03');
});

it('stores a task on an anchor and writes the day it falls on', function () {
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on('2026-08-28')->create();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/aufgaben", [
            'title' => 'Akkus und Karten packen',
            'audience' => 'internal',
            'anchor' => 'shoot:'.$shoot->public_id,
            'offset_days' => -2,
        ])->assertRedirect();

    $task = $project->tasks()->sole();

    expect($task->due_on->format('Y-m-d'))->toBe('2026-08-26')
        ->and($task->due_offset_days)->toBe(-2);
});

it('refuses an anchor the project can no longer reach', function () {
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->cancelled('Abgesagt')->create();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/aufgaben", [
            'title' => 'Ablauf bestätigen',
            'audience' => 'internal',
            'anchor' => 'shoot:'.$shoot->public_id,
            'offset_days' => -2,
        ])->assertStatus(422);
});

it('reopens a completed task', function () {
    $project = Project::factory()->create();
    $task = $project->tasks()->create([
        'title' => 'Anzahlung verbucht',
        'audience' => 'internal',
        'completed_at' => now(),
    ]);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/aufgaben/{$task->public_id}/wieder-oeffnen")
        ->assertRedirect();

    expect($task->fresh()->isDone())->toBeFalse();
});
