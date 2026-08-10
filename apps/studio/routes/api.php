<?php

use App\Gallery\Http\Controllers\AssetCommentController;
use App\Gallery\Http\Controllers\AssetFavoriteController;
use App\Gallery\Http\Controllers\GalleryImageController;
use App\Gallery\Http\Controllers\GalleryReadController;
use App\Http\Controllers\GuestIdentityController;
use App\Http\Controllers\HealthController;
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
