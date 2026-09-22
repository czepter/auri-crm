<?php

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates a staff user with a role, a tenant, and a public id', function () {
    $user = User::factory()->create(['role' => StaffRole::Manager]);

    expect($user->role)->toBe(StaffRole::Manager)
        ->and($user->tenant_id)->toBe(config('tenancy.tenant_id'))
        ->and($user->public_id)->toHaveLength(24)
        ->and($user->hasConfirmedMfa())->toBeFalse();
});

it('hides the internal key and the credential fields when serialising', function () {
    $user = User::factory()->withMfa()->create();

    expect(array_keys($user->toArray()))
        ->not->toContain('id')
        ->not->toContain('password')
        ->not->toContain('mfa_secret')
        ->not->toContain('mfa_recovery_codes');
});

it('reports confirmed mfa once a secret is confirmed', function () {
    $user = User::factory()->withMfa()->create();

    expect($user->hasConfirmedMfa())->toBeTrue()
        ->and($user->mfa_secret)->not->toBeNull();
});

it('encrypts the mfa secret at rest', function () {
    $user = User::factory()->withMfa()->create(['mfa_secret' => 'PLAINTEXTSECRET']);

    $raw = DB::table('users')->where('id', $user->id)->value('mfa_secret');

    expect($raw)->not->toContain('PLAINTEXTSECRET');
});
