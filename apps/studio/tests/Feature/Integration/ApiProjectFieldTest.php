<?php

use App\Crm\Enums\ProjectFieldType;
use App\Crm\Models\Project;
use App\Crm\Models\ProjectField;
use App\Crm\Models\ProjectType;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\AuditLog;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);

    $this->wedding = ProjectType::query()->where('key', 'wedding')->sole();
    $this->guests = ProjectField::factory()->create([
        'project_type_id' => $this->wedding->id,
    ]);
});

function bearer(ApiToken $token): array
{
    return ['Authorization' => 'Bearer '.bearerToken($token)];
}

it('lists the project types with their field definitions', function () {
    $token = ApiToken::factory()->withScopes(Capability::ViewAllProjects)->create();

    $response = $this->withHeaders(bearer($token))->getJson('/api/project-types');

    $response->assertOk();

    $wedding = collect($response->json('data'))->firstWhere('key', 'wedding');

    expect($wedding['fields'])->toHaveCount(1)
        ->and($wedding['fields'][0])->toMatchArray([
            'key' => 'gaeste',
            'type' => 'number',
            'unit' => 'Personen',
            'required' => false,
        ]);

    expect(integerIdPaths($response->json()))->toBe([]);
});

it('leaves a deactivated field out of what a caller may write', function () {
    $this->guests->update(['is_active' => false]);
    $token = ApiToken::factory()->withScopes(Capability::ViewAllProjects)->create();

    $response = $this->withHeaders(bearer($token))->getJson('/api/project-types');

    expect(collect($response->json('data'))->firstWhere('key', 'wedding')['fields'])->toBe([]);
});

it('carries the values in the project it reads back', function () {
    $token = ApiToken::factory()->withScopes(Capability::ViewAllProjects)->create();
    $project = Project::factory()->create(['type' => 'wedding']);
    $project->forceFill(['field_values' => ['gaeste' => 120]])->save();

    $this->withHeaders(bearer($token))
        ->getJson("/api/projects/{$project->public_id}")
        ->assertOk()
        ->assertJsonPath('data.fields.gaeste', 120);
});

it('sets values through the same action the admin screen uses', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = Project::factory()->create(['type' => 'wedding']);

    $this->withHeaders(bearer($token) + ['Idempotency-Key' => 'fields-1'])
        ->patchJson("/api/projects/{$project->public_id}/fields", ['fields' => ['gaeste' => 120]])
        ->assertOk()
        ->assertJsonPath('data.fields.gaeste', 120);

    expect($project->fresh()->field_values)->toBe(['gaeste' => 120]);
});

it('replays an idempotent write rather than writing twice', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = Project::factory()->create(['type' => 'wedding']);

    foreach (range(1, 2) as $attempt) {
        $this->withHeaders(bearer($token) + ['Idempotency-Key' => 'fields-same'])
            ->patchJson("/api/projects/{$project->public_id}/fields", ['fields' => ['gaeste' => 120]])
            ->assertOk()
            ->assertJsonPath('data.fields.gaeste', 120);
    }

    expect(AuditLog::query()->where('event', 'project.fields_changed')->count())->toBe(1);
});

it('names the field in a 422 rather than the request', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = Project::factory()->create(['type' => 'wedding']);

    $this->withHeaders(bearer($token) + ['Idempotency-Key' => 'fields-bad'])
        ->patchJson("/api/projects/{$project->public_id}/fields", ['fields' => ['gaeste' => 'viele']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fields.gaeste');
});

it('refuses to write values without the manage_leads scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ViewAllProjects)->create();
    $project = Project::factory()->create(['type' => 'wedding']);

    $this->withHeaders(bearer($token) + ['Idempotency-Key' => 'fields-scope'])
        ->patchJson("/api/projects/{$project->public_id}/fields", ['fields' => ['gaeste' => 120]])
        ->assertForbidden();
});

it('adds a field to a type with the scope the panel is gated on', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageSettings)->create();

    $this->withHeaders(bearer($token) + ['Idempotency-Key' => 'field-new'])
        ->postJson("/api/project-types/{$this->wedding->public_id}/fields", [
            'label' => 'Location-Art',
            'type' => ProjectFieldType::Select->value,
            'options' => ['Scheune', 'Schloss'],
            'allow_new_options' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.fields.1.key', 'location_art');

    expect($this->wedding->fields()->where('key', 'location_art')->sole()->allow_new_options)->toBeTrue();
});

it('refuses to add a field without the manage_settings scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeaders(bearer($token) + ['Idempotency-Key' => 'field-scope'])
        ->postJson("/api/project-types/{$this->wedding->public_id}/fields", [
            'label' => 'Location-Art',
            'type' => ProjectFieldType::Select->value,
        ])
        ->assertForbidden();
});
