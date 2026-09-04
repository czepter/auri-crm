<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectType as ProjectTypeEnum;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Stage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds every enum case as a stage, keys unchanged', function () {
    expect(Stage::ordered(StagePipeline::Lead)->pluck('key')->all())
        ->toBe(array_column(LeadStage::cases(), 'value'))
        ->and(Stage::ordered(StagePipeline::Project)->pluck('key')->all())
        ->toBe(array_column(ShootStatus::cases(), 'value'))
        ->and(ProjectType::query()->orderBy('sort_order')->pluck('key')->all())
        ->toBe(array_column(ProjectTypeEnum::cases(), 'value'));
});

it('carries the anchors the code branches on', function () {
    expect(Stage::anchored(StagePipeline::Lead, StageAnchor::Won)->key)->toBe('booked')
        ->and(Stage::anchored(StagePipeline::Project, StageAnchor::Delivered)->key)->toBe('delivered')
        ->and(Stage::anchored(StagePipeline::Lead, StageAnchor::Entry)->key)->toBe('new')
        ->and(Stage::anchored(StagePipeline::Project, StageAnchor::Entry)->key)->toBe('requested');
});

it('refuses a second stage claiming the same anchor', function () {
    Stage::factory()->pipeline(StagePipeline::Lead)->anchored(StageAnchor::Won)->create();
})->throws(QueryException::class);

it('lets the same key exist in both pipelines', function () {
    expect(Stage::query()->where('key', 'booked')->count())->toBe(2);
});

it('adds a stage without an anchor', function () {
    $stage = Stage::factory()->create(['sort_order' => 2]);

    expect($stage->anchor)->toBeNull()
        ->and($stage->color->css())->toBe('oklch(48% .07 245)')
        ->and(Stage::ordered(StagePipeline::Lead)->pluck('key'))->toContain('vorgespraech');
});

it('names one project type the default', function () {
    expect(ProjectType::default()->key)->toBe('wedding');
});
