<?php

use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Routes that are deliberately reachable without an audience. Listing them
 * here makes adding a public route a decision someone makes on purpose rather
 * than an omission nobody notices.
 */
const PUBLIC_ROUTES = [
    // The ICS feed is public because a calendar app cannot hold a session. Its
    // credential is the token in the path, and it opens exactly one person's
    // own shooting schedule.
    // The invitation and reset flows are necessarily reachable without a
    // session: the first is used by someone who has no account yet, and the
    // second by someone locked out of the one they have. Both are authorised
    // by a token in the path, and neither grants access on its own — an
    // invited account lands on MFA enrollment, and a reset signs nobody in.
    'admin' => [
        'login',
        'auth/mfa',
        'kalender/{token}/shootings.ics',
        'einladung/{token}',
        'passwort/vergessen',
        'passwort/neu/{token}',
        'passwort/neu',
    ],
    'api' => ['api/health'],
];

// routesForSurface() lives in tests/Pest.php — see the note there.

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
