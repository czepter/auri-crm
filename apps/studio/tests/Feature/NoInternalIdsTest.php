<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Walks a decoded response body and returns the paths of any key that looks
 * like an identifier but holds an integer. Passing a model straight into
 * Inertia::render instead of wrapping it in a Data object is the ordinary way
 * the internal key escapes, and it should fail here rather than in review.
 */
function integerIdPaths(mixed $node, string $path = '$'): array
{
    if (! is_array($node)) {
        return [];
    }

    $found = [];

    foreach ($node as $key => $value) {
        $childPath = $path.'.'.$key;

        if (is_string($key) && (str_ends_with($key, 'id') || str_ends_with($key, 'ids')) && is_int($value)) {
            $found[] = $childPath;
        }

        $found = [...$found, ...integerIdPaths($value, $childPath)];
    }

    return $found;
}

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
