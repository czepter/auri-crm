<?php

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

// #401: book/complete/reopen on the api surface, mirroring the admin
// project screen's own buttons — see App\Crm\Http\Controllers\
// ProjectController::book()/complete()/reopen().
it('books a project, creating its job and advancing its shoots', function () {
    $token = ApiToken::factory()->withScopes(Capability::BookProject)->create();
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'book-1')
        ->postJson("/api/projects/{$project->public_id}/book");

    $response->assertOk();

    expect($response->json('data.projectId'))->toBe($project->public_id)
        ->and($project->job()->exists())->toBeTrue()
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('refuses booking to a token that does not hold BookProject', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'book-forbidden')
        ->postJson("/api/projects/{$project->public_id}/book")
        ->assertForbidden();
});

it('completes a project and reopens it', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'complete-1')
        ->postJson("/api/projects/{$project->public_id}/complete")
        ->assertOk();

    expect($project->fresh()->isCompleted())->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'reopen-1')
        ->deleteJson("/api/projects/{$project->public_id}/complete")
        ->assertOk();

    expect($project->fresh()->isCompleted())->toBeFalse();
});

it('refuses completing an already completed project', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = Project::factory()->create();
    $project->forceFill(['completed_at' => now()])->save();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'complete-twice')
        ->postJson("/api/projects/{$project->public_id}/complete")
        ->assertUnprocessable();
});
