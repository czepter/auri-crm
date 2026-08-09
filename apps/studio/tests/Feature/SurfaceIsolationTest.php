<?php

use App\Support\Surface;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

/**
 * Registers the routes for one surface into a clean collection and returns
 * their URIs. The surface gate is a claim about the route table, so it is
 * tested against the route table rather than against a response code.
 */
function urisForSurface(string $surface): array
{
    config(['surface.name' => $surface]);

    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();

    return collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->sort()
        ->values()
        ->all();
}

it('registers the admin routes only on the admin surface', function () {
    expect(urisForSurface('admin'))->toContain('login')
        ->and(urisForSurface('api'))->not->toContain('login');
});

it('registers the portal routes only on the portal surface', function () {
    // The seam that matters most for the second audience: a staff route does
    // not exist in the client's process at all, so no policy has to hold it.
    expect(urisForSurface('portal'))->toContain('anmelden')
        ->and(urisForSurface('admin'))->not->toContain('anmelden')
        ->and(urisForSurface('portal'))->not->toContain('login')
        ->and(urisForSurface('portal'))->not->toContain('anfragen');
});

it('gives each surface its own session cookie', function () {
    // Two audiences sharing a cookie name on one host is how a browser comes
    // to offer a staff session to the client portal.
    expect(App\Support\Surface::COOKIES)->toHaveCount(count(App\Support\Surface::ALL))
        ->and(array_unique(array_values(App\Support\Surface::COOKIES)))
        ->toHaveCount(count(App\Support\Surface::ALL));
});

it('registers the api routes only on the api surface', function () {
    expect(urisForSurface('api'))->toContain('api/health')
        ->and(urisForSurface('admin'))->not->toContain('api/health');
});

it('gives every surface a non-empty route table', function (string $surface) {
    expect(urisForSurface($surface))->not->toBeEmpty();
})->with(Surface::ALL);

it('refuses to boot on an unknown surface name', function () {
    config(['surface.name' => 'gallery']);

    expect(fn () => Surface::registerRoutes())
        ->toThrow(InvalidArgumentException::class, 'Unknown APP_SURFACE "gallery".');
});

/**
 * Every other test in this file resets the route table with
 * Route::setRoutes(new RouteCollection) and then calls
 * Surface::registerRoutes() directly - that exercises the class's own
 * logic, but it discards whatever the real application boot produced
 * first, so it can never catch a regression in bootstrap/app.php itself
 * (e.g. if `then:` stopped being the only thing that registers routes).
 * This test makes no such call. It asserts against the route table
 * exactly as the framework's own withRouting(..., then: ...) left it for
 * the suite's default (admin) surface.
 */
it('boots with only the admin surface routes registered by the real application bootstrap', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->all();

    expect($uris)->toContain('login')
        ->and($uris)->not->toContain('api/health');
});
