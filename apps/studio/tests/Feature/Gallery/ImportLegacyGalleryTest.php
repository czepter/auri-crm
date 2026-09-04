<?php

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Actions\ImportLegacyGallery;
use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links a legacy gallery to its project without copying any assets', function () {
    $project = Project::factory()->create();

    $result = app(ImportLegacyGallery::class)([
        'project_public_id' => $project->public_id,
        'title' => 'Hochzeit 2014',
        'external_url' => 'https://old-host.example.test/galleries/1234',
    ], 'link');

    expect($result['status'])->toBe('linked');

    $gallery = Gallery::sole();
    expect($gallery->project_id)->toBe($project->id)
        ->and($gallery->is_external)->toBeTrue()
        ->and($gallery->external_url)->toBe('https://old-host.example.test/galleries/1234')
        ->and($gallery->versions()->count())->toBe(0);

    expect($project->galleries)->toHaveCount(1);
});

it('links to a specific shoot when shoot_public_id is given', function () {
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->create();

    app(ImportLegacyGallery::class)([
        'project_public_id' => $project->public_id,
        'shoot_public_id' => $shoot->public_id,
        'external_url' => 'https://old-host.example.test/g/1',
    ], 'link');

    expect(Gallery::sole()->shoot_id)->toBe($shoot->id);
});

it('is idempotent: relinking the same external_url for the same project skips instead of duplicating', function () {
    $project = Project::factory()->create();
    $record = [
        'project_public_id' => $project->public_id,
        'external_url' => 'https://old-host.example.test/g/1',
    ];

    $first = app(ImportLegacyGallery::class)($record, 'link');
    $second = app(ImportLegacyGallery::class)($record, 'link');

    expect($first['status'])->toBe('linked')
        ->and($second['status'])->toBe('skipped')
        ->and(Gallery::count())->toBe(1);
});

it('link mode requires external_url', function () {
    $project = Project::factory()->create();

    $result = app(ImportLegacyGallery::class)(['project_public_id' => $project->public_id], 'link');

    expect($result['status'])->toBe('error')
        ->and(Gallery::count())->toBe(0);
});

it('backfills a legacy gallery with its assets and publishes version 1', function () {
    $project = Project::factory()->create();

    $result = app(ImportLegacyGallery::class)([
        'project_public_id' => $project->public_id,
        'title' => 'Verlobung',
        'assets' => ['a1.jpg', 'a2.jpg'],
    ], 'backfill');

    expect($result['status'])->toBe('backfilled');

    $gallery = Gallery::sole();
    expect($gallery->is_external)->toBeFalse()
        ->and($gallery->external_url)->toBeNull()
        ->and($gallery->currentVersion->version)->toBe(1)
        ->and(collect($gallery->currentVersion->assets)->pluck('key')->all())->toBe(['a1.jpg', 'a2.jpg']);
});

it('is idempotent: backfilling the same project/shoot/title twice skips the second time', function () {
    $project = Project::factory()->create();
    $record = [
        'project_public_id' => $project->public_id,
        'title' => 'Verlobung',
        'assets' => ['a1.jpg'],
    ];

    $first = app(ImportLegacyGallery::class)($record, 'backfill');
    $second = app(ImportLegacyGallery::class)($record, 'backfill');

    expect($first['status'])->toBe('backfilled')
        ->and($second['status'])->toBe('skipped')
        ->and(Gallery::count())->toBe(1);
});

it('backfill mode requires a non-empty assets array', function () {
    $project = Project::factory()->create();

    $result = app(ImportLegacyGallery::class)([
        'project_public_id' => $project->public_id,
        'assets' => [],
    ], 'backfill');

    expect($result['status'])->toBe('error')
        ->and(Gallery::count())->toBe(0);
});

it('errors when project_public_id matches no project', function () {
    $result = app(ImportLegacyGallery::class)(['project_public_id' => 'does-not-exist', 'external_url' => 'https://x.test'], 'link');

    expect($result['status'])->toBe('error');
});

it('errors when shoot_public_id belongs to a different project', function () {
    $project = Project::factory()->create();
    $otherProjectsShoot = Shoot::factory()->create();

    $result = app(ImportLegacyGallery::class)([
        'project_public_id' => $project->public_id,
        'shoot_public_id' => $otherProjectsShoot->public_id,
        'external_url' => 'https://x.test',
    ], 'link');

    expect($result['status'])->toBe('error')
        ->and(Gallery::count())->toBe(0);
});

it('dry run reports what it would do and writes nothing', function () {
    $project = Project::factory()->create();

    $linkResult = app(ImportLegacyGallery::class)([
        'project_public_id' => $project->public_id,
        'external_url' => 'https://old-host.example.test/g/1',
    ], 'link', dryRun: true);

    $backfillResult = app(ImportLegacyGallery::class)([
        'project_public_id' => $project->public_id,
        'title' => 'Verlobung',
        'assets' => ['a1.jpg'],
    ], 'backfill', dryRun: true);

    expect($linkResult['status'])->toBe('would-link')
        ->and($backfillResult['status'])->toBe('would-backfill')
        ->and(Gallery::count())->toBe(0);
});

it('publish() refuses on an external gallery', function () {
    $gallery = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://x.test']);

    expect(fn () => $gallery->publish())->toThrow(LogicException::class);
});
