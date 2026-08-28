<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Stage;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php.
beforeEach(function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff');
});

it('renders both pipelines and the types', function () {
    $this->get('/einstellungen/stufen')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Stages')
            ->has('pipelines', 2)
            ->has('pipelines.0.stages', count(LeadStage::cases()))
            ->has('pipelines.1.stages', count(ShootStatus::cases()))
            ->has('types', 3)
            ->has('colors', 8));
});

it('adds a stage after the one it was placed behind', function () {
    $contacted = stage(LeadStage::Contacted);

    $this->post('/einstellungen/stufen', [
        'pipeline' => StagePipeline::Lead->value,
        'name' => 'Vorgespräch',
        'color' => 'blue',
        'after' => $contacted->public_id,
    ])->assertRedirect('/einstellungen/stufen');

    expect(Stage::ordered(StagePipeline::Lead)->pluck('key')->all())
        ->toBe(['new', 'contacted', 'vorgesprach', 'quote_sent', 'quote_accepted', 'contract_sent', 'booked']);
});

it('renames a stage without moving the records standing on it', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    $this->patch('/einstellungen/stufen/'.stage(LeadStage::QuoteSent)->public_id, [
        'name' => 'Kostenvoranschlag',
        'color' => 'teal',
        'is_active' => true,
    ])->assertRedirect();

    expect($lead->fresh()->stage->key)->toBe('quote_sent')
        ->and($lead->fresh()->stage->name)->toBe('Kostenvoranschlag');
});

it('reorders a pipeline', function () {
    $order = Stage::ordered(StagePipeline::Project)->pluck('public_id')->all();
    [$order[0], $order[1]] = [$order[1], $order[0]];

    $this->patch('/einstellungen/stufen/reihenfolge', [
        'pipeline' => StagePipeline::Project->value,
        'order' => $order,
    ])->assertRedirect();

    expect(Stage::ordered(StagePipeline::Project)->pluck('key')->take(2)->all())->toBe(['booked', 'requested']);
});

it('moves the records along when a stage is deleted', function () {
    $extra = Stage::factory()->pipeline(StagePipeline::Lead)->create(['sort_order' => 2]);
    $lead = Lead::factory()->atStage($extra->key)->create();

    $this->delete('/einstellungen/stufen/'.$extra->public_id, [
        'move_to' => stage(LeadStage::Contacted)->public_id,
    ])->assertRedirect();

    expect(Stage::query()->find($extra->id))->toBeNull()
        ->and($lead->fresh()->stage->key)->toBe('contacted');
});

it('refuses to delete a stage the app branches on', function () {
    $booked = stage(LeadStage::Booked);

    $this->delete('/einstellungen/stufen/'.$booked->public_id)
        ->assertSessionHasErrors('move_to');

    expect(Stage::anchored(StagePipeline::Lead, StageAnchor::Won))->not->toBeNull();
});

it('refuses to delete a stage with records and no target', function () {
    $extra = Stage::factory()->pipeline(StagePipeline::Lead)->create(['sort_order' => 2]);
    Lead::factory()->atStage($extra->key)->create();

    $this->delete('/einstellungen/stufen/'.$extra->public_id)->assertSessionHasErrors('move_to');

    expect(Stage::query()->find($extra->id))->not->toBeNull();
});

it('adds a project type and keeps one default', function () {
    $this->post('/einstellungen/projekttypen', ['name' => 'Elopement'])->assertRedirect();

    $added = ProjectType::query()->where('key', 'elopement')->sole();

    $this->patch('/einstellungen/projekttypen/'.$added->public_id, [
        'name' => 'Elopement',
        'is_active' => true,
        'is_default' => true,
    ])->assertRedirect();

    expect(ProjectType::query()->where('is_default', true)->count())->toBe(1)
        ->and(ProjectType::default()->key)->toBe('elopement');
});

it('refuses to delete a type that projects still carry', function () {
    $type = ProjectType::query()->where('key', 'wedding')->sole();
    Project::factory()->create(['type' => $type->key]);

    $this->delete('/einstellungen/projekttypen/'.$type->public_id)->assertSessionHasErrors('type');

    expect(ProjectType::query()->where('key', 'wedding')->exists())->toBeTrue();
});
