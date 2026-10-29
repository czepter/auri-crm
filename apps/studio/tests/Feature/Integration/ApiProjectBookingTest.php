<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

// #401: book/complete/reopen on the api surface, mirroring the admin
// project screen's own buttons — see App\Crm\Http\Controllers\
// CaseController::book()/complete()/reopen().
it('books a project, advancing its shoots and answering with its detail', function () {
    $token = ApiToken::factory()->withScopes(Capability::BookProject)->create();
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'book-1')
        ->postJson("/api/cases/{$project->public_id}/book");

    $response->assertOk();

    expect($response->json('data.id'))->toBe($project->public_id)
        ->and($response->json('data.bookedAt'))->not->toBeNull()
        ->and($response->json('data.bookedVia'))->toBe('manual')
        ->and($project->fresh()->isBooked())->toBeTrue()
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('refuses booking to a token that does not hold BookCase', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'book-forbidden')
        ->postJson("/api/cases/{$project->public_id}/book")
        ->assertForbidden();
});

it('completes a project and reopens it', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'complete-1')
        ->postJson("/api/cases/{$project->public_id}/complete")
        ->assertOk();

    expect($project->fresh()->isCompleted())->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'reopen-1')
        ->deleteJson("/api/cases/{$project->public_id}/complete")
        ->assertOk();

    expect($project->fresh()->isCompleted())->toBeFalse();
});

it('refuses completing an already completed project', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();
    $project->forceFill(['completed_at' => now()])->save();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'complete-twice')
        ->postJson("/api/cases/{$project->public_id}/complete")
        ->assertUnprocessable();
});
