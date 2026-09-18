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
    // ...and einrichtung is the same shape one step earlier: EnsureNoStaffExists
    // is the credential — reachable only while the tenant has no staff account
    // at all, closed forever the moment one exists.
    'admin' => [
        'login',
        'einrichtung',
        'auth/mfa',
        'kalender/{token}/shootings.ics',
        'einladung/{token}',
        'passwort/vergessen',
        'passwort/neu/{token}',
        'passwort/neu',
        // The profile screen's e-mail change, confirmed from the new inbox —
        // which may not be the browser holding the session, so no audience.
        // The single-use token in the path is the whole credential; it signs
        // nobody in, it swaps one column. Same story as the portal's own
        // e-mail-bestaetigen/{token} below.
        'profil/e-mail-bestaetigen/{token}',
        // The initial account-verification link after studio-owner creation,
        // confirmed from the inbox — which may not be the browser that
        // created the account. The single-use token in the path is the whole
        // credential; it stamps email_verified_at but signs nobody in.
        'e-mail-bestaetigen/{token}',
        // N-04's token exchange: the MCP client calls this directly,
        // off-browser, with no staff session to hold an `audience:` guard —
        // the single-use authorization code and its PKCE verifier are the
        // credential instead. See OAuthTokenController's own doc comment.
        // /oauth/authorize (the half a staff session DOES gate) carries
        // `audience:staff` same as every other screen in the mfa group below,
        // so it is not listed here.
        'oauth/token',
        // ...and the same for RFC 7591 registration and the RFC 8414
        // discovery document: both are read or written before any credential
        // exists, which is what makes them discovery at all.
        'oauth/register',
        '.well-known/oauth-authorization-server',
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
        // N-04: RFC 9728's document, read by a client that holds no token
        // yet to find out who hands one out. Gating it would close the only
        // door in. The MCP endpoint it describes carries `audience:api` like
        // every other route on this surface — see routes/mcp.php.
        '.well-known/oauth-protected-resource',
        '.well-known/oauth-protected-resource/{path}',
        // C-08. The one route in the system a stranger is meant to reach —
        // an audience guard on it would defeat the point. What stands in
        // for one is the honeypot, the timing floor, the per-IP and
        // per-address limits, and a response that never varies; see
        // PublicInquiryController.
        'api/anfragen',
        // E-12's delivery webhook, for the same reason as the Stripe one
        // above it: the caller proves itself with an HMAC over the raw
        // body, a credential 'audience:' has no vocabulary for.
        'api/mail/eingang',
        // G-06's gate on this surface: the password itself is the
        // credential, and what it buys is a signed pass (GalleryViewPass) —
        // neither of which reads as `audience:`.
        'api/galleries/{gallery}/passwort',
        // R39-R42: the pre-release page's own signup — no credential to
        // hold at all, the address is the whole point of the call. Rate
        // limited and uniform-response instead, same shape as the inquiry
        // route above — see GalleryPrereleaseSignupController.
        'api/galleries/{gallery}/benachrichtigung',
        // Die Anmeldung des Gastes. Wie die Gate-Route darüber ist die
        // Adresse selbst das, was geprüft wird — sechs Ziffern in einer
        // Mail —, und was sie einbringt, ist ein signierter Pass
        // (GalleryGuestPass). Keins von beidem liest sich als `audience:`.
        // Gebremst wird stattdessen je Adresse und Galerie, siehe
        // GalleryGuestController.
        'api/galleries/{gallery}/guest',
        'api/galleries/{gallery}/guest/bestaetigung',
        'api/galleries/{gallery}', 'api/gallery-versions/{galleryVersion}/images/{key}',
        'api/gallery-versions/{galleryVersion}/favorites', 'api/gallery-versions/{galleryVersion}/favorites/{key}',
        'api/gallery-versions/{galleryVersion}/comments',
        // R31: the draft's own image route. Its credential is the
        // GalleryPreviewPass query parameter, checked on every request by
        // GalleryDraftImageController itself — the same shape as the
        // password pass above, not an `audience:` guard.
        'api/galleries/{gallery}/entwurf-bilder/{key}',
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
        'anmelden', 'zugang/{token}', 'branding/logo', 'impressum', 'rechtstexte/{text}', 'abmelden/{contact}',
        // The quote share link — reusable, unlike zugang/{token} above, but
        // the same shape otherwise: share_token in the path is the whole
        // credential, checked by QuoteShareController's own lookup rather
        // than an `audience:` guard.
        'angebot/{quote}/teilen', 'angebot/{quote}/teilen/bild/{item}', 'angebot/{quote}/teilen/annehmen',
        // Q-10: das Reglerraster. Oeffentlich aus demselben Grund wie das
        // Logo — Schaufensterbilder laedt der Fotograf hoch, um sie zu
        // zeigen, und das Raster steht, bevor irgendwer angenommen hat. Der
        // Index laesst keinen Dateinamen raten.
        'schaufenster/{index}',
        // P-05 (#224): the e-mail-change confirmation. Whoever follows it is
        // reading the new inbox, which is not necessarily the browser
        // holding the session — same reason zugang/{token} above carries no
        // audience — and the single-use token in the path is the whole
        // credential. It signs nobody in; it swaps one column.
        'e-mail-bestaetigen/{token}',
        // G-06's three gates. A gallery guest is neither a staff user nor a
        // portal Contact — there is no `audience:` guard for it, because the
        // gate check itself (`gallery.gate:*`, EnsureGalleryGate) is that
        // audience's whole authorisation story, the same way the token in
        // `zugang/{token}` above is the client portal's for a guest who
        // cannot sign in yet. Every route past the view gate carries
        // `gallery.gate:view` or `gallery.gate:download` even though that
        // does not read as `audience:` to this test.
        'galerien/{gallery}', 'galerien/{gallery}/passwort',
        // G-09's heart and G-12's comments for the portal's own grid (P-08),
        // behind `gallery.gate:view` — which does not read as `audience:` to
        // this test any more than the routes below do.
        'galerien/{gallery}/beteiligung', 'galerien/{gallery}/favorit',
        'galerien/{gallery}/kommentar',
        // P-08 (#227): the image bytes, bound on the version and gated by
        // its parent gallery's own `gallery.gate:view` — same story as the
        // routes below, and the reason they moved off the api surface at
        // all is that the api surface holds no session to check the claim
        // against (EnsureUngatedGallery).
        'galerie-bilder/{galleryVersion}/{key}',
        'galerien/{gallery}/download', 'galerien/{gallery}/download/pin',
        // The "Herunterladen" mail's own link (GalleryZipReady). Behind the
        // same two gates as the screen it redirects into; the stored,
        // expiring GalleryDownloadLink token it checks first is that link's
        // whole credential, same shape as the tokens above, not `audience:`.
        'galerien/{gallery}/download/mail/{token}',
        // G-03 (#231): queues the archive. Behind the same two gates as the
        // screen that offers it, neither of which reads as `audience:`.
        'galerien/{gallery}/download/vorbereiten',
        'galerien/{gallery}/download/email', 'galerien/{gallery}/download/email/{token}',
        // G-11's delivery endpoint carries its own two-layer check —
        // `gallery.gate:download` plus `signed` — neither of which reads as
        // `audience:` to this test, same as every other gallery route above.
        'galerien/{gallery}/download/{zipBuildPart}/{guestId}',
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
