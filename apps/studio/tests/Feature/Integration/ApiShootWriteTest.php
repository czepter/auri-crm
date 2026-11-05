<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

// A wedding split over two days is one project with two shoots, and
// CaptureInquiry only ever makes the first one.
it('adds a second shooting day to a project', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-1')
        ->postJson("/api/cases/{$project->public_id}/shoots", [
            'kind' => 'Feier',
            'label' => 'Feier',
            'date' => '2019-07-06',
        ]);

    $response->assertCreated();

    expect($response->json('data.kind'))->toBe('Feier')
        ->and($response->json('data.date'))->toBe('2019-07-06')
        ->and($response->json('data.projectId'))->toBe($project->public_id)
        ->and($response->json('data.status'))->toBe(ShootStatus::Requested->value)
        ->and(integerIdPaths($response->json()))->toBe([]);

    // The one CaptureInquiry made stays the primary; a later day is not it.
    expect($project->shoots()->latest('id')->first()->is_primary)->toBeFalse();
});

it('falls back to the project type for a shoot given no kind', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create(['type' => 'wedding']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-no-kind')
        ->postJson("/api/cases/{$project->public_id}/shoots", [
            'date' => '2019-07-06',
        ]);

    $response->assertCreated();

    expect($response->json('data.kind'))->toBe($project->type->name);
});

it('backdates a shooting day entered after the fact', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-backdated')
        ->postJson("/api/cases/{$project->public_id}/shoots", [
            'kind' => 'Feier',
            'date' => '2019-07-06',
            'entered_at' => '2018-12-03',
        ])
        ->assertCreated();

    expect($project->shoots()->latest('id')->first()->status_entered_at->toDateString())->toBe('2018-12-03');
});

it('refuses a shoot to a token that does not hold ManageShoots', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-forbidden')
        ->postJson("/api/cases/{$project->public_id}/shoots", ['kind' => 'Feier'])
        ->assertForbidden();
});

it('moves a shoot through the pipeline and back, reusing AdvanceShootStatus', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked, daysAgo: 11)->on(now()->subDay()->toDateString())->create();
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

// #363/#400: call_time, venue and label had no way back after create. A
// caller resending only the date must not null the venue beside it — the
// same merge LeadCaseController::update()'s admin version fails to hold to.
it('updates a shoot, leaving fields the request omits untouched', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $shoot = Shoot::factory()->create(['venue_name' => 'Schloss Elmau', 'venue_city' => 'Elmau', 'call_time' => '10:00']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-update-1')
        ->patchJson("/api/shoots/{$shoot->public_id}", ['date' => '2019-08-01']);

    $response->assertOk();

    expect($shoot->fresh()->date->toDateString())->toBe('2019-08-01')
        ->and($shoot->fresh()->venue_name)->toBe('Schloss Elmau')
        ->and($shoot->fresh()->call_time)->not->toBeNull()
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('deletes a non-primary shoot but refuses to delete the primary one', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();
    $primary = $project->shoots()->create(['kind' => 'Trauung', 'is_primary' => true]);
    $extra = $project->shoots()->create(['kind' => 'Feier']);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-delete-primary')
        ->deleteJson("/api/shoots/{$primary->public_id}")
        ->assertUnprocessable();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-delete-extra')
        ->deleteJson("/api/shoots/{$extra->public_id}")
        ->assertNoContent();

    expect(Shoot::whereKey($primary->id)->exists())->toBeTrue()
        ->and(Shoot::whereKey($extra->id)->exists())->toBeFalse();
});

it('designates a different shoot as primary', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = ClientCase::factory()->create();
    $primary = $project->shoots()->create(['kind' => 'Trauung', 'is_primary' => true]);
    $extra = $project->shoots()->create(['kind' => 'Feier']);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'shoot-primary-1')
        ->postJson("/api/shoots/{$extra->public_id}/primary")
        ->assertOk();

    expect($primary->fresh()->is_primary)->toBeFalse()
        ->and($extra->fresh()->is_primary)->toBeTrue();
});

it('refuses a shoot write to a token that may only read projects', function () {
    $token = ApiToken::factory()->withScopes(Capability::ViewAllCases)->create();
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'status-scope')
        ->postJson("/api/shoots/{$shoot->public_id}/status", ['status' => ShootStatus::Shot->value])
        ->assertForbidden();

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Booked->value);
});
