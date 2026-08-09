<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Enums\BookedVia;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Documents\Models\DocumentTemplate;
use App\Migration\Actions\CommitHistoricalImport;
use App\Migration\Actions\PlanHistoricalImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cleanFixture(): string
{
    return base_path('tests/Fixtures/migration/clean');
}

function fixtureWithErrors(): string
{
    return base_path('tests/Fixtures/migration/with-errors');
}

it('plans every row of a clean import as creatable, with none unmapped', function () {
    $plan = app(PlanHistoricalImport::class)(cleanFixture());

    expect($plan->contacts['toCreate'])->toHaveCount(2)
        ->and($plan->contacts['errors'])->toBe([])
        ->and($plan->projects['toCreate'])->toHaveCount(1)
        ->and($plan->projects['errors'])->toBe([])
        ->and($plan->jobs['toCreate'])->toHaveCount(1)
        ->and($plan->jobs['errors'])->toBe([])
        ->and($plan->contracts['toCreate'])->toHaveCount(1)
        ->and($plan->contracts['errors'])->toBe([]);

    $project = $plan->projects['toCreate'][0];
    expect($project['type'])->toBe(ProjectType::Wedding)
        ->and($project['primary_contact_ref'])->toBe('c1');

    $job = $plan->jobs['toCreate'][0];
    expect($job['booked_via'])->toBe(BookedVia::Manual);

    $contract = $plan->contracts['toCreate'][0];
    expect($contract['status'])->toBe(ContractStatus::Rendered)
        ->and($contract['rendered_at'])->toBe('2024-04-20');
});

it('commits a clean plan into real, linked records', function () {
    $plan = app(PlanHistoricalImport::class)(cleanFixture());
    $summary = app(CommitHistoricalImport::class)($plan);

    expect($summary)->toBe([
        'contacts' => 2,
        'contacts_reused' => 0,
        'projects' => 1,
        'jobs' => 1,
        'contracts' => 1,
    ]);

    expect(Contact::count())->toBe(2)
        ->and(Project::count())->toBe(1)
        ->and(Job::count())->toBe(1)
        ->and(Contract::count())->toBe(1);

    $project = Project::sole();
    expect($project->name)->toBe('Meier & Kowalski — Hochzeit')
        ->and($project->type)->toBe(ProjectType::Wedding)
        ->and($project->primaryContact()->name)->toBe('Lena Meier');

    $job = Job::sole();
    expect($job->project_id)->toBe($project->id)
        ->and($job->booked_via)->toBe(BookedVia::Manual)
        ->and($job->booked_by_id)->toBeNull()
        ->and($job->booked_at->toDateString())->toBe('2024-05-01');

    $contract = Contract::sole();
    expect($contract->project_id)->toBe($project->id)
        ->and($contract->status)->toBe(ContractStatus::Rendered)
        ->and($contract->rendered_at->toDateString())->toBe('2024-04-20');

    $template = DocumentTemplate::findOrFail($contract->contract_template_id);
    expect($template->name)->toBe('Historischer Import (X-01)')
        ->and($template->is_current)->toBeFalse();
});

it('reuses an existing contact matched by email instead of creating a duplicate', function () {
    Contact::factory()->create(['name' => 'Lena M.', 'email' => 'lena@example.test']);

    $plan = app(PlanHistoricalImport::class)(cleanFixture());

    expect($plan->contacts['toCreate'])->toHaveCount(1)
        ->and($plan->contacts['toReuse'])->toHaveCount(1)
        ->and($plan->contacts['toReuse'][0]['ref'])->toBe('c1');

    app(CommitHistoricalImport::class)($plan);

    expect(Contact::count())->toBe(2);

    $project = Project::sole();
    expect($project->contacts()->wherePivot('role', ProjectContactRole::Primary->value)->first()->email)
        ->toBe('lena@example.test');
});

it('reports unmappable rows with a reason instead of importing them', function () {
    $plan = app(PlanHistoricalImport::class)(fixtureWithErrors());

    expect($plan->contacts['toCreate'])->toHaveCount(1)
        ->and($plan->contacts['errors'])->toHaveCount(1);

    expect($plan->contacts['errors'][0]['row'])->toBe(2)
        ->and($plan->contacts['errors'][0]['reason'])->toBe('name is required');

    expect($plan->projects['toCreate'])->toBe([])
        ->and($plan->projects['errors'])->toHaveCount(1);

    expect($plan->projects['errors'][0]['reason'])
        ->toBe("primary_contact_ref 'c-unknown' not found in contacts.csv");
});

it('never writes anything on a dry run, and writes exactly the plan on --commit', function () {
    $this->artisan('migration:import-historical-data', ['path' => cleanFixture()])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run — nothing written.');

    expect(Contact::count())->toBe(0)
        ->and(Project::count())->toBe(0)
        ->and(Job::count())->toBe(0)
        ->and(Contract::count())->toBe(0);

    $this->artisan('migration:import-historical-data', ['path' => cleanFixture(), '--commit' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Imported 2 contact(s)');

    expect(Contact::count())->toBe(2)
        ->and(Project::count())->toBe(1)
        ->and(Job::count())->toBe(1)
        ->and(Contract::count())->toBe(1);
});

it('reports unmapped rows from the command without writing anything for that entity', function () {
    $this->artisan('migration:import-historical-data', ['path' => fixtureWithErrors()])
        ->assertSuccessful()
        ->expectsOutputToContain('cannot map 1 row(s)')
        ->expectsOutputToContain('row 2 (ref c2): name is required')
        ->expectsOutputToContain("primary_contact_ref 'c-unknown' not found in contacts.csv");

    expect(Contact::count())->toBe(0)
        ->and(Project::count())->toBe(0);
});

it('fails cleanly for a missing input directory', function () {
    $this->artisan('migration:import-historical-data', ['path' => base_path('tests/Fixtures/migration/does-not-exist')])
        ->assertFailed();
});
