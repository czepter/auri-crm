<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is not visible in the portal until someone says so', function () {
    // A raw inquiry must not surface in a client portal the moment it arrives.
    expect(Project::factory()->create()->portal_visible)->toBeFalse();
});

it('casts its type', function () {
    $project = Project::factory()->create(['type' => ProjectType::Wedding]);

    expect($project->fresh()->type)->toBe(ProjectType::Wedding)
        ->and(ProjectType::Wedding->label())->toBe('Hochzeit');
});

it('attaches contacts with roles', function () {
    $project = Project::factory()->create();
    $she = Contact::factory()->create(['name' => 'Annika Steffens']);
    $he = Contact::factory()->create(['name' => 'Ruben Falk']);

    $project->attachContact($she, ProjectContactRole::Primary);
    $project->attachContact($he, ProjectContactRole::Partner);

    expect($project->contacts()->count())->toBe(2)
        ->and($project->primaryContact()->name)->toBe('Annika Steffens');
});

it('fills the seam columns on the pivot row', function () {
    $project = Project::factory()->create();
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);

    $pivot = DB::table('project_contacts')->sole();

    // attach() writes through the query builder, which bypasses the model
    // events BelongsToTenant and HasPublicId hook into — so attachContact
    // fills both by hand, and this is the test that says so.
    expect($pivot->tenant_id)->toBe(config('tenancy.tenant_id'))
        ->and($pivot->public_id)->toHaveLength(24);
});

it('refuses a second primary contact on one project', function () {
    $project = Project::factory()->create();

    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
})->throws(QueryException::class);

it('allows the same person to be primary on two projects', function () {
    $contact = Contact::factory()->create();

    Project::factory()->create()->attachContact($contact, ProjectContactRole::Primary);
    Project::factory()->create()->attachContact($contact, ProjectContactRole::Primary);

    expect($contact->projects()->count())->toBe(2);
});

it('refuses the same person twice in the same role on one project', function () {
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();

    $project->attachContact($contact, ProjectContactRole::Payer);
    $project->attachContact($contact, ProjectContactRole::Payer);
})->throws(QueryException::class);
