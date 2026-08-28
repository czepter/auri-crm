<?php

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Actions\WorkflowColumns;
use App\Crm\Enums\DueAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Crm\Support\CrmVocabulary;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::store('redis')->flush());

/** Der Projekttyp hinter seinem Schluessel; stage() steht schon in Pest.php. */
function projectType(string $key): ProjectType
{
    return app(CrmVocabulary::class)->type($key);
}

it('carries one column per stage of the pipeline the board asked for', function () {
    TaskTemplate::factory()->create([
        'title' => 'Nachfassen',
        'pipeline' => StagePipeline::Lead,
        'stage' => 'contacted',
        'anchor' => DueAnchor::StageEntry,
    ]);

    $payload = app(WorkflowColumns::class)(projectType('wedding'), StagePipeline::Lead);

    expect($payload['columns'])->toHaveCount(app(CrmVocabulary::class)->ordered(StagePipeline::Lead)->count())
        ->and($payload['columns'][1]['templates'])->toHaveCount(1)
        ->and($payload['columns'][1]['templates'][0]->title)->toBe('Nachfassen')
        ->and($payload['columns'][0]['templates'])->toHaveCount(0);
});

it('leaves a template of another project type out of the column', function () {
    TaskTemplate::factory()->create([
        'title' => 'Familienliste anfordern',
        'project_type' => 'wedding',
        'pipeline' => StagePipeline::Lead,
        'stage' => 'new',
    ]);

    $payload = app(WorkflowColumns::class)(projectType('portrait'), StagePipeline::Lead);

    expect($payload['columns'][0]['templates'])->toHaveCount(0);
});

it('keeps the panel off a plain board load — it costs a query nobody asked for', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/anfragen')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props)->not->toHaveKey('workflow');
});

it('writes the task the stage owes when a lead reaches it, counted from the day it did', function () {
    $lead = Lead::factory()->create(['stage_entered_at' => now()]);

    TaskTemplate::factory()->create([
        'title' => 'Angebot nachfassen',
        'pipeline' => StagePipeline::Lead,
        'stage' => 'quote_sent',
        'anchor' => DueAnchor::StageEntry,
        'day_offset' => 5,
    ]);

    app(AdvanceLeadStage::class)($lead, stage('quote_sent'), staffMember(StaffRole::Owner));

    $task = Task::sole();

    expect($task->title)->toBe('Angebot nachfassen')
        ->and($task->shoot_id)->toBeNull()
        ->and($task->due_anchor)->toBe('stage:quote_sent')
        ->and($task->due_offset_days)->toBe(5);
});

it('writes the task once, however often the stage is re-entered', function () {
    $lead = Lead::factory()->create(['stage_entered_at' => now()]);
    $owner = staffMember(StaffRole::Owner);

    TaskTemplate::factory()->create([
        'pipeline' => StagePipeline::Lead,
        'stage' => 'contacted',
        'anchor' => DueAnchor::StageEntry,
    ]);

    // Backwards is legitimate — a mis-drop on the board is corrected by
    // dragging it back — so the second arrival must not double the task.
    app(AdvanceLeadStage::class)($lead, stage('contacted'), $owner);
    app(AdvanceLeadStage::class)($lead, stage('new'), $owner);
    app(AdvanceLeadStage::class)($lead, stage('contacted'), $owner);

    expect(Task::count())->toBe(1);
});

it('refuses a stage that belongs to the other pipeline', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->from('/anfragen')
        ->post('/einstellungen/aufgabenvorlagen', [
            'title' => 'Auswahl freigeben',
            'audience' => 'internal',
            'pipeline' => StagePipeline::Lead->value,
            'stage' => 'culling',
            'day_offset' => 0,
            'anchor' => DueAnchor::StageEntry->value,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('stage');

    expect(TaskTemplate::count())->toBe(0);
});
