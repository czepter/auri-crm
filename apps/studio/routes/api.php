<?php

use App\Crm\Http\Controllers\PublicInquiryController;
use App\Engagement\Http\Controllers\InboundMailController;
use App\Enums\Capability;
use App\Http\Controllers\GuestIdentityController;
use App\Http\Controllers\HealthController;
use App\Integration\Http\Controllers\ContactController;
use App\Integration\Http\Controllers\CrmReadController;
use App\Integration\Http\Controllers\InternalNoteController;
use App\Integration\Http\Controllers\LeadController;
use App\Integration\Http\Controllers\OAuthTokenInfoController;
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

// P-08 (#227) removed the gallery from this surface entirely. It was here
// for apps/gallery, an Astro SSR viewer on its own origin, which is why the
// asset list, the image bytes, the favourites and the comments all had to be
// reachable without a session — and why a view password protected the portal
// screen and nothing else. The grid, the lightbox and both engagement
// endpoints now live on the portal surface behind `gallery.gate:view`
// (routes/portal.php), where the claim actually is.

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

Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->post('/leads/{lead:public_id}/stage', [LeadController::class, 'advanceStage'])
    ->name('api.leads.stage.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageNotes->value, 'idempotent'])
    ->post('/projects/{project:public_id}/notes', [InternalNoteController::class, 'store'])
    ->name('api.projects.notes.store');
