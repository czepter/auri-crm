<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

// #355: the partner a migration run dropped has to be added after capture.
it('adds a partner to an existing project once, reusing a known address', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots, Capability::ManageContacts)->create();
    $project = ClientCase::factory()->create();
    $known = Contact::factory()->create(['email' => 'friedrich@example.test']);

    foreach (['a', 'b'] as $key) {
        $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
            ->withHeader('Idempotency-Key', "partner-{$key}")
            ->postJson("/api/cases/{$project->public_id}/contacts", ['name' => 'Friedrich Rüffer', 'email' => 'friedrich@example.test'])
            ->assertCreated()
            ->assertJson(['id' => $known->public_id, 'role' => 'partner']);
    }

    expect($project->contacts()->get()->map(fn ($c) => [$c->id, $c->pivot->role])->all())
        ->toBe([[$known->id, CaseContactRole::Partner->value]]);
});

it('accepts a role outside the enum, like the admin screen does', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots, Capability::ManageContacts)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'officiant')
        ->postJson("/api/cases/{$project->public_id}/contacts", ['name' => 'Pastor Klein', 'role' => 'Trauredner'])
        ->assertCreated()
        ->assertJson(['role' => 'Trauredner']);
});

it('refuses a second primary contact', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots, Capability::ManageContacts)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'primary')
        ->postJson("/api/cases/{$project->public_id}/contacts", ['name' => 'X', 'role' => 'primary'])
        ->assertUnprocessable();
});

it('refuses a leads token', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'leads')
        ->postJson("/api/cases/{$project->public_id}/contacts", ['name' => 'X'])
        ->assertForbidden();
});

it('needs manage_contacts to create a new contact', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();
    $known = Contact::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'new')
        ->postJson("/api/cases/{$project->public_id}/contacts", ['name' => 'Neu', 'email' => 'neu@example.test'])
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'known')
        ->postJson("/api/cases/{$project->public_id}/contacts", ['contact_id' => $known->public_id])
        ->assertCreated();
});
