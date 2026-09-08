<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

it('moves a shoot through the pipeline and back, reusing AdvanceShootStatus', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked, daysAgo: 11)->create();
    $entered = $shoot->status_entered_at;

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'status-1')
        ->postJson("/api/shoots/{$shoot->public_id}/status", ['status' => ShootStatus::Shot->value])
        ->assertOk();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'status-2')
        ->postJson("/api/shoots/{$shoot->public_id}/status", ['status' => ShootStatus::Booked->value]);

    $response->assertOk();

    expect($response->json('status'))->toBe(ShootStatus::Booked->value)
        ->and($shoot->fresh()->status_entered_at->diffInSeconds($entered))->toBeLessThan(2)
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('cancels a shoot and takes the absage back', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'cancel-1')
        ->postJson("/api/shoots/{$shoot->public_id}/cancel", ['reason' => 'Termin geplatzt'])
        ->assertOk();

    expect($shoot->fresh()->isCancelled())->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'uncancel-1')
        ->postJson("/api/shoots/{$shoot->public_id}/uncancel")
        ->assertOk();

    expect($shoot->fresh()->isCancelled())->toBeFalse();
});

it('refuses a shoot write to a token that may only read projects', function () {
    $token = ApiToken::factory()->withScopes(Capability::ViewAllProjects)->create();
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'status-scope')
        ->postJson("/api/shoots/{$shoot->public_id}/status", ['status' => ShootStatus::Shot->value])
        ->assertForbidden();

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Booked->value);
});
