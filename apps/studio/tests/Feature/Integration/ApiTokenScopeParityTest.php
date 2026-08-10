<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Integration\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * I-01's central claim: scopes are the S-06 role policies expressed as
 * token scopes, never a vocabulary of their own. Proven the most literal
 * way possible — a token scoped exactly like a role's own capability list
 * agrees with that role, capability by capability, for every role.
 */
it('lets a token scoped like a staff role reach exactly what that role reaches', function (StaffRole $role) {
    $token = ApiToken::factory()->create([
        'scopes' => array_map(fn (Capability $c) => $c->value, $role->capabilities()),
    ]);

    foreach (Capability::cases() as $capability) {
        expect($token->allows($capability))->toBe($role->allows($capability));
    }
})->with(StaffRole::cases());

it('refuses every capability once revoked, regardless of scope', function () {
    $token = ApiToken::factory()->create(['scopes' => array_map(fn (Capability $c) => $c->value, Capability::cases())]);
    $token->revoke();

    foreach (Capability::cases() as $capability) {
        expect($token->allows($capability))->toBeFalse();
    }
});

it('refuses to hold a scope that is not a known capability', function () {
    expect(fn () => ApiToken::factory()->create(['scopes' => ['not_a_real_capability']]))
        ->toThrow(InvalidArgumentException::class, 'Unknown capability scope "not_a_real_capability".');
});
