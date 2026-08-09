<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// integerIdPaths() lives in tests/Pest.php — see the note there.

it('never puts an integer identifier into an authenticated inertia page prop', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the login page', function () {
    $props = $this->get('/login')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('exposes the signed-in user as a public id', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['auth']['user']['id'])->toBe($user->public_id)
        ->and($props['auth']['user'])->not->toHaveKey('mfa_secret');
});

it('detects an integer id when one is present', function () {
    expect(integerIdPaths(['data' => ['id' => 7, 'name' => 'x']]))->toBe(['$.data.id']);
    expect(integerIdPaths(['data' => ['id' => 'abc', 'name' => 'x']]))->toBe([]);
});
