<?php

use App\Gallery\Http\Controllers\DownloadZipPartController;
use App\Gallery\Http\Controllers\GalleryDownloadController;
use App\Gallery\Http\Controllers\GalleryImageController;
use App\Gallery\Http\Controllers\GalleryPasswordController;
use App\Portal\Http\Controllers\BrandingController;
use App\Portal\Http\Controllers\ContractController;
use App\Portal\Http\Controllers\HomeController;
use App\Portal\Http\Controllers\ImpressumController;
use App\Portal\Http\Controllers\MagicLinkController;
use App\Portal\Http\Controllers\MailboxController;
use App\Portal\Http\Controllers\PaymentCheckoutController;
use App\Portal\Http\Controllers\PaymentController;
use App\Portal\Http\Controllers\ProjectController;
use App\Portal\Http\Controllers\QuoteController;
use App\Portal\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// Client surface. A different audience from the studio's, on its own session
// cookie and its own guard — a Contact is not a User, and no session
// established here or there is worth anything on the other.

// T-08's maintenance closure wraps every route below it, signed-in or not —
// see EnsurePortalMaintenanceClosure. The branding logo and the Impressum
// sit outside it, further down this file: a portal a studio has closed still
// needs its logo and its legal notice reachable from the closure screen.
Route::middleware('portal.maintenance')->group(function () {
    // The way in. Public by necessity: someone who cannot sign in is the only
    // person who ever needs it. Listed in the audience matrix's PUBLIC_ROUTES,
    // so it stays a decision rather than an omission.
    Route::middleware('guest:portal')->group(function () {
        Route::get('/anmelden', [MagicLinkController::class, 'create'])->name('portal.login');
        Route::post('/anmelden', [MagicLinkController::class, 'store'])->name('portal.login.request');

        // The link itself. Single use, fifteen minutes, and the token in the
        // path is the whole of the credential — so it is spent the moment it
        // works.
        Route::get('/zugang/{token}', [MagicLinkController::class, 'consume'])->name('portal.access');
    });

    Route::middleware('audience:portal')->group(function () {
        Route::post('/abmelden', [MagicLinkController::class, 'destroy'])->name('portal.logout');

        // The switcher. Zero visible projects is an empty state here rather
        // than an error, and exactly one skips the picker by redirecting
        // straight to it — see HomeController.
        Route::get('/', HomeController::class)->name('portal.home');

        // Direct entry. Bound on the public id and re-asking the same
        // visibility scope HomeController asks — see ProjectController — so a
        // project the contact is not on, or one the studio has not made
        // portal_visible, is a 404 here exactly as it is absent from the
        // switcher. Never a 403: the difference between "not yours" and
        // "does not exist" is not this surface's to give away.
        Route::get('/projekte/{project}', ProjectController::class)->name('portal.projects.show');

        // P-03 (#222). The portal's first write path onto a project. Open
        // only while nothing is reserved — a booked project answers 404 here
        // rather than 422, the same shape every other closed door on this
        // surface takes. See UpdateProjectDetails for the audit row and the
        // line it writes into the conversation.
        Route::patch('/projekte/{project}/angaben', [ProjectController::class, 'update'])
            ->name('portal.projects.details.update');

        // Mockup 07. Reads only the artifact M-01's queued job already
        // stored — see App\Portal\Http\Controllers\ContractController.
        Route::get('/projekte/{project}/vertrag', [ContractController::class, 'show'])
            ->name('portal.contracts.show');
        Route::get('/projekte/{project}/vertrag/pdf', [ContractController::class, 'download'])
            ->name('portal.contracts.download');

        // M-02's signing rail. Writes at most one contract_signatures row per
        // contract — see App\Contracts\Actions\SignContract. Gated by T-08's
        // own "sign contracts" switch (default off) — a 404 identical to a
        // route that does not exist when it is off, regardless of whether
        // SignContract itself would otherwise allow it.
        Route::post('/projekte/{project}/vertrag/unterschreiben', [ContractController::class, 'sign'])
            ->middleware('portal.capability:sign_contracts')
            ->name('portal.contracts.sign');

        // Q-07. Reads Project::latestQuote() — Q-06's own frozen snapshot,
        // never re-derived — see App\Portal\Http\Controllers\QuoteController.
        // The view stays open the same way a contract's view does; only the
        // accept action below is gated.
        Route::get('/projekte/{project}/angebot', [QuoteController::class, 'show'])
            ->name('portal.quotes.show');

        // The accept action. Re-checks validity against the row this request
        // fetches, not against whatever the last GET rendered — see
        // AcceptQuote. Gated by T-08's own "accept quotes online" switch
        // (default off), same 404 rule as the signing route above — Q-07
        // being fully functional is not itself the studio's consent to
        // offer it in the portal.
        Route::post('/projekte/{project}/angebot/annehmen', [QuoteController::class, 'accept'])
            ->middleware('portal.capability:accept_quotes')
            ->name('portal.quotes.accept');

        // E-06, gated by T-08's own "messaging" switch (default off) — the
        // whole mailbox, GET and POST alike, the same full-screen shape
        // payment_status above takes rather than gating only a submit the
        // way accept_quotes/sign_contracts do. See EnsurePortalCapability
        // and StudioSettingsResolver::messagingEnabled().
        //
        // P-04 (#223): one mailbox, no project in the path. A thread names a
        // booking or it does not, and the route can no longer require what
        // the thread itself may not have — see MailboxController.
        Route::middleware('portal.capability:messaging')->group(function () {
            Route::get('/nachrichten', [MailboxController::class, 'index'])->name('portal.threads.index');
            Route::post('/nachrichten', [MailboxController::class, 'store'])->name('portal.threads.store');
            Route::post('/nachrichten/{thread}', [MailboxController::class, 'reply'])->name('portal.threads.reply');
        });

        // T-07. Read-only apart from M-04's own confirm step on return from
        // Stripe — nothing here ever CREATES a Stripe object, see
        // App\Portal\Http\Controllers\PaymentController. Gated by T-08's own
        // "see payment status" switch (default off), same 404 rule as above.
        Route::get('/projekte/{project}/zahlungen', PaymentController::class)
            ->middleware('portal.capability:payment_status')
            ->name('portal.payments.show');

        // M-04. The client's own click, and the only route that ever creates
        // a Stripe object — see App\Payments\Actions\
        // CreateInstallmentCheckoutSession. Same "see payment status" gate as
        // the screen it is reached from: nothing about paying an installment
        // should be reachable once that screen itself is not.
        Route::post('/projekte/{project}/zahlungen/{installment}/jetzt-zahlen', PaymentCheckoutController::class)
            ->middleware('portal.capability:payment_status')
            ->name('portal.payments.checkout');

        // M-06. The invoice itself, behind the same gate as the screen that
        // links to it — a client who may not see payment status may not
        // download the paperwork behind it either.
        Route::get('/projekte/{project}/zahlungen/{installment}/rechnung', [PaymentController::class, 'invoice'])
            ->middleware('portal.capability:payment_status')
            ->name('portal.payments.invoice');
    });
});

// The stored logo, public: a brand mark is not a secret, and it has to
// resolve on the sign-in screen before any contact session exists. Reachable
// during a maintenance closure too, same reasoning as the Impressum below.
Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('portal.branding.logo');

// § 5 TMG. Public and reachable regardless of the maintenance closure above —
// see ImpressumController and EnsurePortalMaintenanceClosure's own note.
Route::get('/impressum', ImpressumController::class)->name('portal.impressum');

// G-06: the gallery guest's own way in — a different audience from the
// client above (no Contact, no `audience:portal` session; see
// AudienceMatrixTest's PUBLIC_ROUTES for why these carry no `audience:`
// declaration). Sits outside portal.maintenance's closure entirely: a client
// portal closed for maintenance says nothing about whether a couple's guests
// can still reach their gallery.
// P-08 (#227): the image bytes, on the surface that holds the view claim.
// Registered before the `galerien/{gallery}` group below, which would
// otherwise swallow the path as a gallery public_id. Bound on the version,
// not the gallery — a published version's contents never change, which is
// what lets GalleryImageController's response claim `immutable` — so
// EnsureGalleryGate resolves the parent gallery from it.
Route::get('/galerie-bilder/{galleryVersion}/{key}', GalleryImageController::class)
    ->where('key', '.*')
    ->middleware('gallery.gate:view')
    ->name('portal.gallery.image');

Route::prefix('galerien/{gallery}')->group(function () {
    // The view gate (mockup 08). Its own screen decides unlocked-or-not
    // rather than being wrapped by `gallery.gate:view` — see
    // GalleryPasswordController's own doc comment on why.
    Route::get('/', [GalleryPasswordController::class, 'show'])->name('gallery.show');
    Route::post('/passwort', [GalleryPasswordController::class, 'store'])->name('gallery.password.store');

    // The download gates (mockups 11, 12) — reachable only once the view
    // gate above is satisfied, since "download" is a link found inside an
    // already-unlocked gallery, never before it.
    Route::middleware('gallery.gate:view')->group(function () {
        Route::get('/download', [GalleryDownloadController::class, 'show'])->name('gallery.download.show');
        Route::post('/download/pin', [GalleryDownloadController::class, 'storePin'])->name('gallery.download.pin');

        // The email gate is the second of the two download gates — a guest
        // who has not yet cleared the PIN has no business requesting or
        // spending an email-verification link either.
        Route::middleware('gallery.gate:download')->group(function () {
            Route::post('/download/email', [GalleryDownloadController::class, 'requestEmail'])
                ->name('gallery.download.email.request');
            Route::get('/download/email/{token}', [GalleryDownloadController::class, 'verifyEmail'])
                ->name('gallery.download.email.verify');

            // G-11 (#60): the delivery endpoint itself. guestId rides in the
            // path rather than as an ordinary query parameter — Laravel's
            // `signed` middleware hashes the whole query string a link was
            // minted with, so binding the requester's identity into the
            // signed URL this way is what makes it tamper-evident rather
            // than merely opaque. `signed` and `gallery.gate:download` both
            // apply: a guest needs the PIN cleared AND a link nobody could
            // have forged or outlived.
            Route::get('/download/{zipBuildPart}/{guestId}', DownloadZipPartController::class)
                ->where('guestId', '[^/]{1,64}')
                ->middleware('signed')
                ->name('gallery.download.part');
        });
    });
});

// T-11's unsubscribe. Public and reachable regardless of the maintenance
// closure above — the same reasoning as branding and Impressum: a client
// following a link out of a mail already delivered has no reason to first
// clear a closure screen — with the contact's own public_id as the whole
// credential, same as every other token-in-the-path route this file names.
Route::get('/abmelden/{contact}', [UnsubscribeController::class, 'show'])->name('portal.unsubscribe.show');
Route::post('/abmelden/{contact}', [UnsubscribeController::class, 'store'])->name('portal.unsubscribe.store');
