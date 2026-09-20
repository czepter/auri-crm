<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
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
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = Project::factory()->create();
    $known = Contact::factory()->create(['email' => 'friedrich@example.test']);

    foreach (['a', 'b'] as $key) {
        $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
            ->withHeader('Idempotency-Key', "partner-{$key}")
            ->postJson("/api/projects/{$project->public_id}/contacts", ['name' => 'Friedrich Rüffer', 'email' => 'friedrich@example.test'])
            ->assertCreated()
            ->assertJson(['id' => $known->public_id, 'role' => 'partner']);
    }

    expect($project->contacts()->get()->map(fn ($c) => [$c->id, $c->pivot->role])->all())
        ->toBe([[$known->id, ProjectContactRole::Partner->value]]);
});

it('accepts a role outside the enum, like the admin screen does', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'officiant')
        ->postJson("/api/projects/{$project->public_id}/contacts", ['name' => 'Pastor Klein', 'role' => 'Trauredner'])
        ->assertCreated()
        ->assertJson(['role' => 'Trauredner']);
});

it('refuses a second primary contact', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'primary')
        ->postJson("/api/projects/{$project->public_id}/contacts", ['name' => 'X', 'role' => 'primary'])
        ->assertUnprocessable();
});
