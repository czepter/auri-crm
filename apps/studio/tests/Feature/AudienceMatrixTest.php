<?php

use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Routes that are deliberately reachable without an audience. Listing them
 * here makes adding a public route a decision someone makes on purpose rather
 * than an omission nobody notices.
 */
const PUBLIC_ROUTES = [
    'admin' => ['login', 'auth/mfa'],
    'api' => ['api/health'],
];

function routesForSurface(string $surface): array
{
    config(['surface.name' => $surface]);

    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();

    return collect(Route::getRoutes()->getRoutes())->all();
}

it('declares an audience on every non-public route of every surface', function (string $surface) {
    $undeclared = collect(routesForSurface($surface))
        ->reject(fn ($route) => in_array($route->uri(), PUBLIC_ROUTES[$surface], true))
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))
        ->map(fn ($route) => $route->methods()[0].' '.$route->uri())
        ->values()
        ->all();

    expect($undeclared)->toBe([]);
})->with(Surface::ALL);

it('rejects unauthenticated access to staff routes', function () {
    $this->get('/')->assertRedirect('/login');
    $this->post('/logout')->assertRedirect('/login');
});

it('rejects a session authenticated against a different guard', function () {
    $user = User::factory()->withMfa()->create();

    // Authenticated as `web`, but the route declares `staff`.
    $this->actingAs($user, 'web');

    $this->get('/')->assertRedirect('/login');
});

it('accepts a correctly authenticated staff session', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff');

    $this->get('/')->assertOk();
});
