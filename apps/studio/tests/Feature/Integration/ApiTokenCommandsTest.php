<?php

use App\Integration\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mints an ApiToken with the given name, scopes and rate limit', function () {
    $this->artisan('api-token:create', [
        'name' => 'Zapier integration',
        '--scope' => ['manage_contacts', 'manage_leads'],
        '--rate-limit' => 30,
    ])->assertSuccessful();

    $token = ApiToken::query()->where('name', 'Zapier integration')->sole();

    expect($token->scopes)->toBe(['manage_contacts', 'manage_leads'])
        ->and($token->rate_limit_per_minute)->toBe(30);
});

it('defaults the rate limit to 60 requests per minute', function () {
    $this->artisan('api-token:create', ['name' => 'Default limit token'])->assertSuccessful();

    expect(ApiToken::query()->where('name', 'Default limit token')->sole()->rate_limit_per_minute)->toBe(60);
});

it('refuses to mint a token scoped to an unknown capability', function () {
    $this->artisan('api-token:create', [
        'name' => 'Bad scope token',
        '--scope' => ['not_a_real_capability'],
    ])->assertFailed();

    expect(ApiToken::query()->where('name', 'Bad scope token')->exists())->toBeFalse();
});

it('revokes an ApiToken by its public_id', function () {
    $token = ApiToken::factory()->create();

    $this->artisan('api-token:revoke', ['public_id' => $token->public_id])->assertSuccessful();

    expect($token->fresh()->isRevoked())->toBeTrue();
});

it('fails when revoking an unknown public_id', function () {
    $this->artisan('api-token:revoke', ['public_id' => 'does-not-exist'])->assertFailed();
});
