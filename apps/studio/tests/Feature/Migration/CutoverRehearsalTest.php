<?php

use App\Contracts\Models\Contract;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;

/*
 * X-03's "exercised at least once against a copy of production" acceptance
 * criterion. There is no real production copy in this sandboxed environment
 * — this is the automated rehearsal docs/cutover-runbook.md points at
 * instead: the actual cutover sequence, against the real test database,
 * through real pg_dump / pg_restore processes rather than mocks.
 *
 * Deliberately does NOT `uses(RefreshDatabase::class)`. RefreshDatabase
 * wraps each test in a transaction on the app's own connection and rolls it
 * back afterwards — invisible to pg_dump, which opens its own connection and
 * only ever sees committed data. Every write below commits for real, which
 * is what lets the final assertions prove db:cutover-restore actually put
 * the database back, rather than a rolled-back transaction faking it.
 *
 * Runs `migrate` itself up front rather than relying on another test file
 * having done it first — this file's tests can run before any
 * RefreshDatabase-using file in suite order, and `migrate` is a no-op past
 * the first run either way.
 */

it('runs the cutover sequence end to end: snapshot, commit imports, verify, roll back', function () {
    $this->artisan('migrate')->run();

    $baseline = [
        'contacts' => Contact::count(),
        'projects' => Project::count(),
        'jobs' => Job::count(),
        'contracts' => Contract::count(),
        'galleries' => Gallery::count(),
    ];

    $snapshotPath = storage_path('app/private/cutover/rehearsal-'.uniqid().'.dump');

    // 1. Pre-cutover snapshot — the rollback point the runbook takes right
    // before the commit-mode imports run.
    $this->artisan('db:cutover-snapshot', ['--path' => $snapshotPath])
        ->assertSuccessful();

    expect(file_exists($snapshotPath))->toBeTrue();

    // 2. X-01: historical CRM data, commit mode.
    $this->artisan('migration:import-historical-data', [
        'path' => base_path('tests/Fixtures/migration/clean'),
        '--commit' => true,
    ])->assertSuccessful();

    expect(Contact::count())->toBe($baseline['contacts'] + 2)
        ->and(Project::count())->toBe($baseline['projects'] + 1)
        ->and(Job::count())->toBe($baseline['jobs'] + 1)
        ->and(Contract::count())->toBe($baseline['contracts'] + 1);

    $project = Project::query()->latest('id')->first();

    // 3. X-02: legacy galleries, commit mode (no --dry-run), backfilling the
    // project X-01 just created — the same shape as a real cutover, where
    // the gallery import runs against projects the CRM import just made.
    $galleryFile = tempnam(sys_get_temp_dir(), 'cutover-galleries-').'.json';
    file_put_contents($galleryFile, json_encode([
        ['project_public_id' => $project->public_id, 'title' => 'Verlobung', 'assets' => ['a1.jpg']],
    ]));

    $this->artisan('gallery:import-legacy', ['file' => $galleryFile, '--mode' => 'backfill'])
        ->assertSuccessful();

    unlink($galleryFile);

    expect(Gallery::count())->toBe($baseline['galleries'] + 1);

    // 4. Data landed — this is the state a failed cutover would otherwise
    // leave behind, and exactly what the rollback below has to undo.
    expect(Contact::count())->toBeGreaterThan($baseline['contacts']);

    // 5. Rollback — the runbook's go/no-go failure path.
    $this->artisan('db:cutover-restore', ['path' => $snapshotPath, '--force' => true])
        ->assertSuccessful();

    // 6. The database is back exactly where it started.
    expect(Contact::count())->toBe($baseline['contacts'])
        ->and(Project::count())->toBe($baseline['projects'])
        ->and(Job::count())->toBe($baseline['jobs'])
        ->and(Contract::count())->toBe($baseline['contracts'])
        ->and(Gallery::count())->toBe($baseline['galleries']);

    unlink($snapshotPath);
});

it('fails cleanly when asked to restore a snapshot that does not exist', function () {
    $this->artisan('db:cutover-restore', ['path' => '/tmp/does-not-exist-cutover.dump', '--force' => true])
        ->assertFailed();
});

it('aborts without restoring when the confirmation is declined', function () {
    $this->artisan('db:cutover-restore', ['path' => base_path('composer.json')])
        ->expectsConfirmation('This overwrites every table in \''.config('database.connections.pgsql.database').'\' with the snapshot at '.base_path('composer.json').'. Continue?', 'no')
        ->assertSuccessful()
        ->expectsOutputToContain('Aborted — nothing restored.');
});

it('rejects a non-pgsql connection for both commands', function () {
    $this->artisan('db:cutover-snapshot', ['--connection' => 'sqlite'])
        ->assertFailed()
        ->expectsOutputToContain("is not pgsql");

    $this->artisan('db:cutover-restore', ['path' => base_path('composer.json'), '--connection' => 'sqlite'])
        ->assertFailed()
        ->expectsOutputToContain("is not pgsql");
});
