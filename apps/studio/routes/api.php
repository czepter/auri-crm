<?php

use App\Enums\Capability;
use App\Gallery\Http\Controllers\AssetCommentController;
use App\Gallery\Http\Controllers\AssetFavoriteController;
use App\Gallery\Http\Controllers\GalleryImageController;
use App\Gallery\Http\Controllers\GalleryReadController;
use App\Http\Controllers\GuestIdentityController;
use App\Http\Controllers\HealthController;
use App\Integration\Http\Controllers\ContactController;
use App\Integration\Http\Controllers\CrmReadController;
use App\Integration\Http\Controllers\InternalNoteController;
use App\Integration\Http\Controllers\LeadController;
use App\Payments\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Public JSON surface. In P1a this carries the health probe only; P1b adds
// the public inquiry endpoint and P3 adds the gallery read API.
Route::get('/health', HealthController::class);

// G-08: the Astro gallery viewer's two calls — the currently-published
// version's metadata, and the image bytes it points at. No `audience:api`
// guard on either yet: G-06 (#57) is the story that decides how a guest
// earns the right to see one gallery, and wraps these routes with that
// check when it lands. Until then this surface assumes reachability, same
// as every other still-open gate in this phase.
Route::get('/galleries/{gallery:public_id}', GalleryReadController::class)->name('api.galleries.show');
Route::get('/gallery-versions/{galleryVersion:public_id}/images/{key}', GalleryImageController::class)
    ->where('key', '.*')
    ->name('api.gallery-versions.images.show');

// G-09: the lightbox's favorite toggle — see AssetFavoriteController's own
// doc comment for the guest_id it trusts and why. The first browser-
// originated call this surface serves (the two routes above are only ever
// hit server-side, by Astro's own SSR fetch, or from a bare <img> src), so
// it's also what config/cors.php exists for.
Route::get('/gallery-versions/{galleryVersion:public_id}/favorites', [AssetFavoriteController::class, 'index'])
    ->name('api.gallery-versions.favorites.index');
Route::post('/gallery-versions/{galleryVersion:public_id}/favorites/{key}', [AssetFavoriteController::class, 'toggle'])
    ->where('key', '.*')
    ->name('api.gallery-versions.favorites.toggle');

// G-12: the lightbox's comment rail — asset_key travels as a request field
// on both, not a path segment, since a comment (unlike a favorite) is never
// addressed by its own key/guest pair. Same guest-identity gap as the
// favorite routes above, inherited on purpose rather than solved here — see
// AssetCommentController's own doc comment.
Route::get('/gallery-versions/{galleryVersion:public_id}/comments', [AssetCommentController::class, 'index'])
    ->name('api.gallery-versions.comments.index');
Route::post('/gallery-versions/{galleryVersion:public_id}/comments', [AssetCommentController::class, 'store'])
    ->name('api.gallery-versions.comments.store');

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
