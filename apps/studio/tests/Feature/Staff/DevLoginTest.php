<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The dev login is a complete authentication bypass — no password, no MFA
 * challenge. These assert the guards rather than the convenience: the happy
 * path is a button someone presses locally and sees work immediately, but the
 * day this route answers outside local is the day it matters.
 *
 * The suite runs in the testing environment, so "not local" is the real
 * condition here rather than a mocked one.
 */
it('has no route behind it outside local', function () {
    expect(app()->environment('local'))->toBeFalse();

    $this->post('/dev-login')->assertNotFound();
});

it('is absent from the route table outside local', function () {
    // Not merely unreachable — never registered. A 404 could also mean a typo
    // in the path, which would pass the test above while the route existed.
    $names = collect(routesForSurface('admin'))->map(fn ($route) => $route->uri())->all();

    expect($names)->not->toContain('dev-login');
});

it('does not offer the button on the login page outside local', function () {
    // A staff account has to exist for /login to render at all — an empty
    // table sends every visitor to /einrichtung instead. See SetupTest.
    User::factory()->create();

    $props = $this->get('/login')->assertOk()->viewData('page')['props'];

    expect($props['devLogin'])->toBeFalse();
});

it('creates no account outside local', function () {
    $this->post('/dev-login')->assertNotFound();

    expect(App\Models\User::where('email', 'dev@studio.test')->exists())->toBeFalse();
});
