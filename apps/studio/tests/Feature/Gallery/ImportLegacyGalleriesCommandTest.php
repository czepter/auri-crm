<?php

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Writes $records as a JSON fixture to a temp file and returns its path. */
function legacyGalleryFixture(array $records): string
{
    $path = tempnam(sys_get_temp_dir(), 'legacy-galleries-').'.json';
    file_put_contents($path, json_encode($records));

    return $path;
}

it('links legacy galleries from a JSON fixture', function () {
    $project = Project::factory()->create();
    $file = legacyGalleryFixture([
        ['project_public_id' => $project->public_id, 'external_url' => 'https://old-host.example.test/g/1'],
    ]);

    $this->artisan('gallery:import-legacy', ['file' => $file, '--mode' => 'link'])
        ->expectsOutputToContain('linked')
        ->assertSuccessful();

    expect(Gallery::sole()->is_external)->toBeTrue();
    unlink($file);
});

it('backfills legacy galleries from a JSON fixture', function () {
    $project = Project::factory()->create();
    $file = legacyGalleryFixture([
        ['project_public_id' => $project->public_id, 'title' => 'Verlobung', 'assets' => ['a1.jpg']],
    ]);

    $this->artisan('gallery:import-legacy', ['file' => $file, '--mode' => 'backfill'])
        ->expectsOutputToContain('backfilled')
        ->assertSuccessful();

    $gallery = Gallery::sole();
    expect($gallery->is_external)->toBeFalse()
        ->and($gallery->currentVersion->version)->toBe(1);
    unlink($file);
});

it('a backfilled or linked legacy gallery is reachable from its project either way', function () {
    $linkedProject = Project::factory()->create();
    $backfilledProject = Project::factory()->create();
    $file = legacyGalleryFixture([
        ['project_public_id' => $linkedProject->public_id, 'external_url' => 'https://old-host.example.test/g/1'],
    ]);
    $backfillFile = legacyGalleryFixture([
        ['project_public_id' => $backfilledProject->public_id, 'title' => 'Verlobung', 'assets' => ['a1.jpg']],
    ]);

    $this->artisan('gallery:import-legacy', ['file' => $file, '--mode' => 'link'])->assertSuccessful();
    $this->artisan('gallery:import-legacy', ['file' => $backfillFile, '--mode' => 'backfill'])->assertSuccessful();

    expect($linkedProject->galleries()->sole()->is_external)->toBeTrue()
        ->and($backfilledProject->galleries()->sole()->currentVersion->version)->toBe(1);

    unlink($file);
    unlink($backfillFile);
});

it('dry run reports without writing any gallery', function () {
    $project = Project::factory()->create();
    $file = legacyGalleryFixture([
        ['project_public_id' => $project->public_id, 'external_url' => 'https://old-host.example.test/g/1'],
    ]);

    $this->artisan('gallery:import-legacy', ['file' => $file, '--mode' => 'link', '--dry-run' => true])
        ->expectsOutputToContain('[dry run]')
        ->assertSuccessful();

    expect(Gallery::count())->toBe(0);
    unlink($file);
});

it('rejects an unknown mode', function () {
    $file = legacyGalleryFixture([]);

    $this->artisan('gallery:import-legacy', ['file' => $file, '--mode' => 'nonsense'])
        ->assertFailed();

    unlink($file);
});

it('fails cleanly when the fixture file does not exist', function () {
    $this->artisan('gallery:import-legacy', ['file' => '/tmp/does-not-exist-legacy-galleries.json'])
        ->assertFailed();
});

it('fails cleanly when the fixture file is not a valid JSON array', function () {
    $path = tempnam(sys_get_temp_dir(), 'legacy-galleries-').'.json';
    file_put_contents($path, 'not json');

    $this->artisan('gallery:import-legacy', ['file' => $path])
        ->assertFailed();

    unlink($path);
});

it('summarizes mixed outcomes across a run', function () {
    $project = Project::factory()->create();
    $record = ['project_public_id' => $project->public_id, 'external_url' => 'https://old-host.example.test/g/1'];
    $file = legacyGalleryFixture([$record, $record, ['project_public_id' => 'missing', 'external_url' => 'https://x.test']]);

    $this->artisan('gallery:import-legacy', ['file' => $file, '--mode' => 'link'])
        ->expectsOutputToContain('1 linked, 1 skipped, 1 error')
        ->assertSuccessful();

    unlink($file);
});
