<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

function contributorToken(array $abilities): array
{
    $user = staffMember(StaffRole::Contributor);

    return [$user, 'Bearer '.$user->createToken('mcp', $abilities)->plainTextToken];
}

it('refuses a contributor token on a project they are not crewed on', function () {
    [, $bearer] = contributorToken([Capability::ManageNotes->value, Capability::ManageLeads->value]);
    $project = ClientCase::factory()->create();
    InternalNote::factory()->for($project, 'case')->create();

    $this->withHeader('Authorization', $bearer)->getJson("/api/cases/{$project->public_id}/notes")->assertForbidden();
    $this->withHeader('Authorization', $bearer)->withHeader('Idempotency-Key', 'k1')
        ->deleteJson("/api/cases/{$project->public_id}")->assertForbidden();

    expect(ClientCase::count())->toBe(1);
});

it('serves a contributor token on a project they are crewed on', function () {
    [$user, $bearer] = contributorToken([Capability::ManageNotes->value]);
    $project = ClientCase::factory()->create();
    CrewAssignment::factory()->for(Shoot::factory()->for($project, 'case')->create())->for($user)->create();

    $this->withHeader('Authorization', $bearer)->getJson("/api/cases/{$project->public_id}/notes")->assertOk();
});

it('lists only the leads a contributor token may see', function () {
    [$user, $bearer] = contributorToken([Capability::ManageLeads->value]);
    $crewed = ClientCase::factory()->create();
    Lead::factory()->for($crewed, 'case')->create();
    Lead::factory()->for(ClientCase::factory()->create(), 'case')->create();
    CrewAssignment::factory()->for(Shoot::factory()->for($crewed, 'case')->create())->for($user)->create();

    $this->withHeader('Authorization', $bearer)->getJson('/api/leads')
        ->assertOk()->assertJsonCount(1, 'data');
});

it('refuses a token whose owner no longer holds the capability', function () {
    $user = staffMember(StaffRole::Manager);
    $bearer = 'Bearer '.$user->createToken('mcp', [Capability::ManageSettings->value])->plainTextToken;

    $this->withHeader('Authorization', $bearer)->getJson('/api/studio-settings')->assertForbidden();
});
