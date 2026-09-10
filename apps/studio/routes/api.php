<?php

use App\Crm\Http\Controllers\PublicInquiryController;
use App\Engagement\Http\Controllers\InboundMailController;
use App\Enums\Capability;
use App\Gallery\Http\Controllers\AssetCommentController;
use App\Gallery\Http\Controllers\AssetFavoriteController;
use App\Gallery\Http\Controllers\GalleryDraftImageController;
use App\Gallery\Http\Controllers\GalleryGuestController;
use App\Gallery\Http\Controllers\GalleryImageController;
use App\Gallery\Http\Controllers\GalleryPassController;
use App\Gallery\Http\Controllers\GalleryPrereleaseSignupController;
use App\Gallery\Http\Controllers\GalleryReadController;
use App\Http\Controllers\GuestIdentityController;
use App\Http\Controllers\HealthController;
use App\Integration\Http\Controllers\ContactController;
use App\Integration\Http\Controllers\CrmReadController;
use App\Integration\Http\Controllers\InternalNoteController;
use App\Integration\Http\Controllers\LeadController;
use App\Integration\Http\Controllers\OAuthTokenInfoController;
use App\Integration\Http\Controllers\ProjectController;
use App\Integration\Http\Controllers\ProjectFieldController;
use App\Integration\Http\Controllers\ShootController;
use App\Payments\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Public JSON surface. In P1a this carried the health probe only; P3 added
// the gallery read API, and C-08 finally added the public inquiry endpoint
// this comment had been promising since P1b.
Route::get('/health', HealthController::class);

// C-08. No session, no token, no audience guard: this is the one route in
// the system a stranger is supposed to reach. Everything that protects it —
// the honeypot, the timing floor, the per-IP and per-address limits, and the
// uniform 202 that never says which of them applied — lives in the
// controller, because they are one decision rather than a stack of them.
Route::post('/anfragen', PublicInquiryController::class)->name('api.inquiries.store');

// E-12. The inbound provider's delivery webhook. No audience guard for the
// same reason the Stripe webhook beside it has none: the caller is a machine
// proving itself with an HMAC over the raw body, which is a credential this
// app's audience middleware has no vocabulary for.
Route::post('/mail/eingang', InboundMailController::class)->name('api.mail.inbound');

// G-06's view gate, answered here so apps/gallery can render its own gate
// and keep a guest on the fast viewer the whole way. Runs the same
// GallerySecretGate the portal's gate does — one implementation of "is this
// the password", rate limits included — and hands back a signed pass.
Route::post('/galleries/{gallery}/passwort', GalleryPassController::class)->name('api.galleries.pass');

// R39-R42: the pre-release page's signup — public, no password (R40: there
// is nothing behind this address to protect), uniform response (R41: see
// the controller's own doc comment), rate-limited the same way the route
// above is.
Route::post('/galleries/{gallery}/benachrichtigung', GalleryPrereleaseSignupController::class)
    ->name('api.galleries.prerelease-signup');

// G-08/G-09/G-12: everything the Astro gallery viewer calls — version
// metadata, image bytes, favorites, comments. All of it behind
// `gallery.pass`: this surface holds no session, so G-06's claim reaches it
// as a signed bearer instead (GalleryViewPass). A gallery with no password
// needs none. Without this the password protected one screen and left both
// the asset list and the bytes open to anyone holding an id.
//
// R35/R37: a GalleryPreviewPass authorises exactly two things — reading the
// draft and fetching a draft image — and both routes sit outside this
// middleware group entirely rather than inside it. Two different reasons
// land on the same shape: `gallery.pass` guards a whole group of
// version-scoped routes (favorites, comments, version images) a preview
// pass has no business opening (R35), and GalleryReadController must honour
// a preview pass even when the gallery also requires a view password —
// the whole point of a preview link is to reach someone before the
// gallery is published, often before there is a reason to know its
// password at all (R37). Sitting outside the group and checking both
// kinds of pass itself is what makes that possible without leaking the
// preview pass into the other routes here. See both controllers' own doc
// comments, and GuardsGalleryViewPassword for the check GalleryReadController
// shares with this middleware rather than duplicating.
Route::get('/galleries/{gallery}/entwurf-bilder/{key}', GalleryDraftImageController::class)
    ->where('key', '.*')
    ->name('api.galleries.draft-images.show');

// Implicit binding, not {gallery:public_id}: apps/gallery's own /g/{id}
// route accepts a slug or a public_id (Gallery::resolveRouteBinding()),
// and this is the read call it makes with whichever one is in the URL.
Route::get('/galleries/{gallery}', GalleryReadController::class)->name('api.galleries.show');

// Die Anmeldung des Gastes. Hinter dem Ansichtspass, weil sie nur etwas ist,
// das man in einer offenen Galerie tut — aber vor jedem Herz und jedem
// Kommentar, denn ohne sie gibt es keinen Absender.
Route::middleware('gallery.pass')->group(function () {
    Route::post('/galleries/{gallery}/guest', [GalleryGuestController::class, 'request'])
        ->name('api.galleries.guest.request');
    Route::post('/galleries/{gallery}/guest/bestaetigung', [GalleryGuestController::class, 'confirm'])
        ->name('api.galleries.guest.confirm');
});

Route::middleware('gallery.pass')->group(function () {
    Route::get('/gallery-versions/{galleryVersion:public_id}/images/{key}', GalleryImageController::class)
        ->where('key', '.*')
        ->name('api.gallery-versions.images.show');

    // The first browser-originated calls this surface serves (the route
    // above is only ever hit server-side, by Astro's own SSR fetch, or from
    // a bare <img> src), so they are also what config/cors.php exists for.
    Route::middleware('gallery.feature:lists')->group(function () {
        Route::get('/gallery-versions/{galleryVersion:public_id}/favorites', [AssetFavoriteController::class, 'index'])
            ->name('api.gallery-versions.favorites.index');
        Route::post('/gallery-versions/{galleryVersion:public_id}/favorites/{key}', [AssetFavoriteController::class, 'toggle'])
            ->where('key', '.*')
            ->name('api.gallery-versions.favorites.toggle');
    });

    // asset_key travels as a request field on the comment routes, not a path
    // segment, since a comment (unlike a favorite) is never addressed by its
    // own key/guest pair. Same guest-identity gap as the favorite routes,
    // inherited on purpose rather than solved here — see
    // AssetCommentController's own doc comment.
    Route::middleware('gallery.feature:comments')->group(function () {
        Route::get('/gallery-versions/{galleryVersion:public_id}/comments', [AssetCommentController::class, 'index'])
            ->name('api.gallery-versions.comments.index');
        Route::post('/gallery-versions/{galleryVersion:public_id}/comments', [AssetCommentController::class, 'store'])
            ->name('api.gallery-versions.comments.store');
    });
});

// M-05: the first api-surface consumer of user-facing business logic
// (everything above is infrastructure). No `auth` middleware — Stripe is the
// only caller, and it authenticates itself, at the controller, by the
// request signature — see StripeWebhookController's own doc comment.
Route::post('/stripe/webhook', StripeWebhookController::class)->name('api.stripe.webhook');

// G-01: the guest-token guard's own proof of life. Every audience declares
// itself the same way on every surface — see EnsureAudience — and `api` is
// no different: a Sanctum bearer token minted on a Contact (App\Support\
// PersonalAccessToken), not a session, but authenticated and gated exactly
// like `audience:staff` and `audience:portal` are on their own surfaces.
// Nothing beyond identity lives here on purpose — see the controller's own
// doc comment on why this stays the surface's only route until P6.
Route::middleware('audience:api')->get('/whoami', GuestIdentityController::class)->name('api.whoami');

// N-04: what the MCP server's own HTTP transport calls to verify a bearer
// token before accepting a connection (packages/mcp/src/httpAuth.ts) — see
// OAuthTokenInfoController's own doc comment for why this is a second route
// rather than whoami growing a branch for a principal it was never about.
Route::middleware('audience:api')->get('/oauth/token-info', OAuthTokenInfoController::class)->name('api.oauth.token-info');

// I-02: read-only pages over the CRM core for an ApiToken caller (I-01) —
// see CrmReadController's own doc comment for the cursor-pagination and
// `since=` shape every one of these shares. Each scope reuses the exact
// Capability that already gates the matching admin screen (ContactPolicy,
// LeadPolicy, ProjectPolicy) rather than inventing an API-only vocabulary —
// a token can never read something no staff role could. Shoots and jobs
// have no policy of their own; both hang off a project (ShootPolicy already
// delegates to ProjectPolicy, and a Job is a project's booking), so both
// reuse ViewAllProjects, the capability that already means "see every
// project" rather than only the ones you are crewed on.
Route::middleware('audience:api')->group(function () {
    Route::middleware('scope:'.Capability::ManageContacts->value)
        ->get('/contacts', [CrmReadController::class, 'contacts'])->name('api.contacts.index');
    Route::middleware('scope:'.Capability::ViewAllProjects->value)
        ->get('/projects', [CrmReadController::class, 'projects'])->name('api.projects.index');
    Route::middleware('scope:'.Capability::ManageLeads->value)
        ->get('/leads', [CrmReadController::class, 'leads'])->name('api.leads.index');
    Route::middleware('scope:'.Capability::ViewAllProjects->value)
        ->get('/shoots', [CrmReadController::class, 'shoots'])->name('api.shoots.index');
    Route::middleware('scope:'.Capability::ViewAllProjects->value)
        ->get('/jobs', [CrmReadController::class, 'jobs'])->name('api.jobs.index');
});

// N-02: the two reads the MCP server's tool surface needs that I-02's index
// routes above don't answer on their own — a single project by public_id,
// and a lead-stage funnel summary. Same `audience:api` + `scope:` shape as
// every route in the group above, reusing the exact Capability the matching
// index route already requires (project.show is ViewAllProjects, same as
// projects.index; pipeline-snapshot is ManageLeads, same as leads.index,
// since it's a count over the same rows).
Route::middleware('audience:api')->group(function () {
    Route::middleware('scope:'.Capability::ViewAllProjects->value)
        ->get('/projects/{project:public_id}', [CrmReadController::class, 'project'])
        ->name('api.projects.show');
    Route::middleware('scope:'.Capability::ManageLeads->value)
        ->get('/pipeline-snapshot', [CrmReadController::class, 'pipelineSnapshot'])
        ->name('api.pipeline-snapshot');
});

// I-03: the api surface's first write endpoints — a workflow runner with a
// retry policy is the caller, so every one of these carries `idempotent`
// (App\Http\Middleware\EnsureIdempotentWrite) after `scope:`, in that order:
// a caller with the wrong scope must never even claim an idempotency key. All
// four reuse the exact Action or model call the matching admin-surface
// controller already makes — see each controller's own doc comment for which
// one and why an ApiToken can still make it without a staff User to attribute
// it to.
Route::middleware(['audience:api', 'scope:'.Capability::ManageContacts->value, 'idempotent'])
    ->post('/contacts', [ContactController::class, 'store'])
    ->name('api.contacts.store');

Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->post('/leads', [LeadController::class, 'store'])
    ->name('api.leads.store');

// Ahead of the {lead} routes below only for readability — `bulk` is a static
// segment and could not be matched as a public id anyway.
Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->post('/leads/bulk', [LeadController::class, 'storeBulk'])
    ->name('api.leads.bulk.store');

Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->post('/leads/{lead:public_id}/stage', [LeadController::class, 'advanceStage'])
    ->name('api.leads.stage.update');

// Closing a lead and taking that back are the same authority as moving it:
// all three write Lead columns nobody but AdvanceLeadStage writes.
Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->post('/leads/{lead:public_id}/lost', [LeadController::class, 'markLost'])
    ->name('api.leads.lost');

Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->post('/leads/{lead:public_id}/reopen', [LeadController::class, 'reopen'])
    ->name('api.leads.reopen');

// The production pipeline. ManageShoots rather than the read side's
// ViewAllProjects: ShootPolicy::update already routes there, and a token that
// may see every project must not thereby move one through delivery.
Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/projects/{project:public_id}/shoots', [ShootController::class, 'store'])
    ->name('api.projects.shoots.store');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/shoots/{shoot:public_id}/status', [ShootController::class, 'advance'])
    ->name('api.shoots.status.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/shoots/{shoot:public_id}/cancel', [ShootController::class, 'cancel'])
    ->name('api.shoots.cancel');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/shoots/{shoot:public_id}/uncancel', [ShootController::class, 'uncancel'])
    ->name('api.shoots.uncancel');

// Taking an import back — see ProjectController. ManageLeads, the scope that
// created the project through leads.store: whoever may capture a run may undo
// it, and no narrower capability exists for "may delete" (I-01).
Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->delete('/projects/{project:public_id}', [ProjectController::class, 'destroy'])
    ->name('api.projects.destroy');

Route::middleware(['audience:api', 'scope:'.Capability::ManageNotes->value, 'idempotent'])
    ->post('/projects/{project:public_id}/notes', [InternalNoteController::class, 'store'])
    ->name('api.projects.notes.store');

// C-25: what a project of a given type can carry, and the values it does.
// Reading the definitions is ViewAllProjects, the same scope the project
// index already requires — a field definition says nothing a project does
// not. Writing values is ManageLeads, the scope leads.store already carries
// for writing project data; a per-field scope would be a vocabulary the
// staff roles have no counterpart for (I-01). Adding a definition is
// ManageSettings, the capability the admin panel is gated on.
Route::middleware(['audience:api', 'scope:'.Capability::ViewAllProjects->value])
    ->get('/project-types', [ProjectFieldController::class, 'types'])
    ->name('api.project-types.index');

Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->patch('/projects/{project:public_id}/fields', [ProjectFieldController::class, 'updateValues'])
    ->name('api.projects.fields.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageSettings->value, 'idempotent'])
    ->post('/project-types/{projectType:public_id}/fields', [ProjectFieldController::class, 'store'])
    ->name('api.project-types.fields.store');
