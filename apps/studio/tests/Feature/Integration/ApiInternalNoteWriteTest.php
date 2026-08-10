<?php

use App\Crm\Data\InternalNoteData;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

it('adds an internal note to a project, authorless', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageNotes)->create();
    $project = Project::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'note-1')
        ->postJson("/api/projects/{$project->public_id}/notes", ['body' => 'Gegenlicht ab 20 Uhr.']);

    $response->assertCreated()->assertJson(['body' => 'Gegenlicht ab 20 Uhr.']);

    $note = InternalNote::sole();
    expect($note->project_id)->toBe($project->id)
        ->and($note->author_id)->toBeNull();

    expect(integerIdPaths($response->json()))->toBe([]);
});

it('renders an api-authored note with an authorless label on the admin project screen', function () {
    $project = Project::factory()->create();
    $note = InternalNote::factory()->for($project)->create(['author_id' => null]);

    expect(InternalNoteData::fromNote($note)->author)->toBe('API');
});

it('refuses to add a note without the manage_notes scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'note-scope')
        ->postJson("/api/projects/{$project->public_id}/notes", ['body' => 'x'])
        ->assertForbidden();

    expect(InternalNote::count())->toBe(0);
});
