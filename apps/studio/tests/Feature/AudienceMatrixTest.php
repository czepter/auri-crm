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
    // Stripe is the only caller of the webhook, and it authenticates itself
    // by the request signature (Stripe-Signature, verified against
    // config('services.stripe.webhook_secret')) rather than by a session an
    // `audience:` middleware could gate — there is no staff or portal user
    // on this request to hold one. See StripeWebhookController's own doc
    // comment.
    // G-08's own scope note: a gallery guest holds no `audience:api` token
    // yet — G-06 (#57) is the story that decides how one is earned (a PIN,
    // an emailed link, a session) and wraps these two routes with that
    // check. Until it lands, both are deliberately reachable by public_id
    // alone, same as every other still-open gate in this phase. G-09's two
    // favorite routes below inherit the same gap on purpose rather than
    // solving it themselves — see AssetFavoriteController's own doc comment
    // for why an `audience:api` bearer token doesn't fit an anonymous
    // gallery guest anyway, gate or no gate. G-12's two comment routes
    // inherit the same gap, for the same reason — see
    // AssetCommentController's own doc comment.
    'api' => [
        'api/health', 'api/stripe/webhook',
        'api/galleries/{gallery}', 'api/gallery-versions/{galleryVersion}/images/{key}',
        'api/gallery-versions/{galleryVersion}/favorites', 'api/gallery-versions/{galleryVersion}/favorites/{key}',
        'api/gallery-versions/{galleryVersion}/comments',
    ],

    // The client's way in. Public by necessity — the only person who needs it
    // is one who cannot sign in — and the token in the path is the whole
    // credential, spent the moment it works. branding/logo and impressum are
    // T-08's: a brand mark is not a secret, and a legal notice has to be
    // reachable from any page a client can see, including one it has not
    // signed into yet. abmelden/{contact} is T-11's unsubscribe: reached from
    // a mail already delivered, with the contact's own public_id as the whole
    // credential, same shape as zugang/{token}.
    'portal' => [
        'anmelden', 'zugang/{token}', 'branding/logo', 'impressum', 'abmelden/{contact}',
        // G-06's three gates. A gallery guest is neither a staff user nor a
        // portal Contact — there is no `audience:` guard for it, because the
        // gate check itself (`gallery.gate:*`, EnsureGalleryGate) is that
        // audience's whole authorisation story, the same way the token in
        // `zugang/{token}` above is the client portal's for a guest who
        // cannot sign in yet. Every route past the view gate carries
        // `gallery.gate:view` or `gallery.gate:download` even though that
        // does not read as `audience:` to this test.
        'galerien/{gallery}', 'galerien/{gallery}/passwort',
        'galerien/{gallery}/download', 'galerien/{gallery}/download/pin',
        'galerien/{gallery}/download/email', 'galerien/{gallery}/download/email/{token}',
    ],
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
