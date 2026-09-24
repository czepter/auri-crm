<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is not visible in the portal until someone says so', function () {
    // A raw inquiry must not surface in a client portal the moment it arrives.
    expect(ClientCase::factory()->create()->portal_visible)->toBeFalse();
});

it('casts its type', function () {
    $project = ClientCase::factory()->create(['type' => ProjectType::Wedding]);

    expect($project->fresh()->type->key)->toBe(ProjectType::Wedding->value)
        ->and(ProjectType::Wedding->label())->toBe('Hochzeit');
});

it('attaches contacts with roles', function () {
    $project = ClientCase::factory()->create();
    $she = Contact::factory()->create(['name' => 'Annika Steffens']);
    $he = Contact::factory()->create(['name' => 'Ruben Falk']);

    $project->attachContact($she, CaseContactRole::Primary);
    $project->attachContact($he, CaseContactRole::Partner);

    expect($project->contacts()->count())->toBe(2)
        ->and($project->primaryContact()->name)->toBe('Annika Steffens');
});

it('fills the seam columns on the pivot row', function () {
    $project = ClientCase::factory()->create();
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);

    $pivot = DB::table('case_contacts')->sole();

    // attach() writes through the query builder, which bypasses the model
    // events BelongsToTenant and HasPublicId hook into — so attachContact
    // fills both by hand, and this is the test that says so.
    expect($pivot->tenant_id)->toBe(config('tenancy.tenant_id'))
        ->and($pivot->public_id)->toHaveLength(24);
});

it('refuses a second primary contact on one project', function () {
    $project = ClientCase::factory()->create();

    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
})->throws(QueryException::class);

it('allows the same person to be primary on two projects', function () {
    $contact = Contact::factory()->create();

    ClientCase::factory()->create()->attachContact($contact, CaseContactRole::Primary);
    ClientCase::factory()->create()->attachContact($contact, CaseContactRole::Primary);

    expect($contact->cases()->count())->toBe(2);
});

it('refuses the same person twice in the same role on one project', function () {
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();

    $project->attachContact($contact, CaseContactRole::Payer);
    $project->attachContact($contact, CaseContactRole::Payer);
})->throws(QueryException::class);
