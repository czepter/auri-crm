<?php

use App\Gallery\Http\Controllers\DownloadZipPartController;
use App\Gallery\Http\Controllers\GalleryDownloadController;
use App\Gallery\Http\Controllers\GalleryEngagementController;
use App\Gallery\Http\Controllers\GalleryImageController;
use App\Gallery\Http\Controllers\GalleryPasswordController;
use App\Portal\Http\Controllers\AccountController;
use App\Portal\Http\Controllers\BrandingController;
use App\Portal\Http\Controllers\GalleryController as PortalGalleryController;
use App\Portal\Http\Controllers\HomeController;
use App\Portal\Http\Controllers\ImpressumController;
use App\Portal\Http\Controllers\LegalTextController;
use App\Portal\Http\Controllers\MagicLinkController;
use App\Portal\Http\Controllers\MailboxController;
use App\Portal\Http\Controllers\PaymentCheckoutController;
use App\Portal\Http\Controllers\PaymentController;
use App\Portal\Http\Controllers\ProjectController;
use App\Portal\Http\Controllers\QuestionnaireController;
use App\Portal\Http\Controllers\QuoteController;
use App\Portal\Http\Controllers\QuoteShareController;
use App\Portal\Http\Controllers\ServiceController;
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
    });

    // The link itself. Single use, fifteen minutes, and the token in the
    // path is the whole of the credential — so it is spent the moment it
    // works. Deliberately outside `guest:portal`: behind it an already signed
    // in browser was bounced to '/' with the token unspent, so a studio member
    // previewing a second client kept seeing the first, and a shared browser
    // showed one client another's portal.
    Route::get('/zugang/{token}', [MagicLinkController::class, 'consume'])->name('portal.access');

    // The "abgespecktes Portal" a quote's share link opens — no session, no
    // portal_visible, no contact required. share_token in the path is the
    // whole credential, the same shape as /zugang/{token} above, except this
    // one is reusable rather than spent on first use (QuoteShareController).
    Route::get('/angebot/{quote:share_token}/teilen', [QuoteShareController::class, 'show'])
        ->name('portal.quotes.share.show');
    Route::get('/angebot/{quote:share_token}/teilen/bild/{item}', [QuoteShareController::class, 'image'])
        ->name('portal.quotes.share.image');
    Route::post('/angebot/{quote:share_token}/teilen/annehmen', [QuoteShareController::class, 'accept'])
        ->name('portal.quotes.share.accept');

    Route::middleware('audience:portal')->group(function () {
        Route::post('/abmelden', [MagicLinkController::class, 'destroy'])->name('portal.logout');

        // P-09 (#228): ends a preview. A GET because a preview session may
        // not POST — which is the whole point of it.
        Route::get('/vorschau-beenden', [MagicLinkController::class, 'leavePreview'])
            ->name('portal.preview.leave');

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

        // P-03 (#222): the questionnaire the inquiry screen has always linked
        // to and nobody could fill in — E-02/E-03 built the whole family
        // admin-only. Only one the studio actually shared
        // (form_instances.shared_at), on a booking this contact is on; a 404
        // otherwise, because a studio's internal checklist lives on the same
        // table.
        Route::get('/projekte/{project}/fragebogen/{questionnaire}', [QuestionnaireController::class, 'show'])
            ->name('portal.questionnaires.show');
        Route::post('/projekte/{project}/fragebogen/{questionnaire}/datei', [QuestionnaireController::class, 'upload'])
            ->name('portal.questionnaire.upload');
        Route::get('/projekte/{project}/fragebogen/{questionnaire}/datei/{file}', [QuestionnaireController::class, 'download'])
            ->name('portal.questionnaire.file.download');
        Route::delete('/projekte/{project}/fragebogen/{questionnaire}/datei/{file}', [QuestionnaireController::class, 'destroyFile'])
            ->name('portal.questionnaire.file.destroy');
        Route::patch('/projekte/{project}/fragebogen/{questionnaire}', [QuestionnaireController::class, 'update'])
            ->name('portal.questionnaires.update');

        // Q-07. Reads Project::latestQuote() — Q-06's own frozen snapshot,
        // never re-derived — see App\Portal\Http\Controllers\QuoteController.
        // The view stays open; only the accept action below is gated.
        Route::get('/projekte/{project}/angebot/bild/{item}', [QuoteController::class, 'image'])
            ->name('portal.quotes.image');
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

        // Aftersales: was das Studio einem Bestandskunden über seine
        // Buchungen hinaus anbietet, und der eine Klick, der daraus eine
        // Anfrage macht. Hinter T-08s eigenem Schalter (Vorgabe aus), und
        // anders als bei accept_quotes hängt hier auch die Ansicht daran —
        // ein Regal, das ein Studio nicht aufgemacht hat, soll aussehen wie
        // eine Funktion, die es nicht gibt.
        Route::middleware('portal.capability:offers')->group(function () {
            Route::get('/leistungen/{package}', [ServiceController::class, 'show'])
                ->name('portal.services.show');
            Route::get('/leistungen/{package}/bild', [ServiceController::class, 'image'])
                ->name('portal.services.image');
            Route::post('/leistungen/{package}/anfragen', [ServiceController::class, 'request'])
                ->name('portal.services.request');
        });

        // P-05 (#224): the account the portal never had. Contact details are
        // editable whatever phase a booking is in — those are the client's
        // own particulars, not contract content, which is the line P-03 and
        // P-07 draw for the booking's own fields.
        Route::get('/einstellungen', [AccountController::class, 'show'])->name('portal.account.show');
        Route::patch('/einstellungen', [AccountController::class, 'update'])->name('portal.account.update');
        Route::post('/einstellungen/post', [AccountController::class, 'updateSubscription'])
            ->name('portal.account.subscription');
        Route::post('/einstellungen/abmelden-ueberall', [AccountController::class, 'endSessions'])
            ->name('portal.account.sessions.end');
        Route::post('/einstellungen/datenschutz', [AccountController::class, 'requestDsgvo'])
            ->name('portal.account.dsgvo');

        // P-06 (#225): what the couple may do to their own gallery. Actions
        // only — the screen they belong to is the gallery itself, one page
        // for both audiences (GalleryPasswordController). Every one is a
        // 404 for anybody who is not the client of this gallery.
        Route::prefix('/galerien/{gallery}/eure-auswahl')->group(function () {
            Route::middleware('gallery.feature:lists')->group(function () {
                Route::post('/listen', [PortalGalleryController::class, 'storeList'])->name('portal.galleries.lists.store');
                Route::patch('/listen/{list}', [PortalGalleryController::class, 'renameList'])
                    ->name('portal.galleries.lists.rename');
                Route::delete('/listen/{list}', [PortalGalleryController::class, 'destroyList'])
                    ->name('portal.galleries.lists.destroy');
                Route::post('/listen/{list}/bild', [PortalGalleryController::class, 'toggleItem'])
                    ->name('portal.galleries.lists.toggle');
            });

            // The three rotate*() methods have lived on the model since G-06
            // with no caller outside the admin. Rotating is the only real
            // answer to a link that travelled too far.
            Route::post('/tore/{gate}', [PortalGalleryController::class, 'rotateGate'])
                ->name('portal.galleries.gate.rotate');
        });

        // E-06, gated by T-08's own "messaging" switch (default off) — the
        // whole mailbox, GET and POST alike, the same full-screen shape
        // payment_status takes rather than gating only a submit the way
        // accept_quotes does. See EnsurePortalCapability and
        // StudioSettingsResolver::messagingEnabled().
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

        // #419. The invoice as a page, in the studio's own layout — the PDF
        // below is the same document to keep rather than to read.
        Route::get('/projekte/{project}/zahlungen/{invoice}', [PaymentController::class, 'show'])
            ->middleware('portal.capability:payment_status')
            ->name('portal.payments.invoice-page');

        // M-06. The invoice itself, behind the same gate as the screen that
        // links to it — a client who may not see payment status may not
        // download the paperwork behind it either.
        Route::get('/projekte/{project}/zahlungen/{invoice}/rechnung', [PaymentController::class, 'invoice'])
            ->middleware('portal.capability:payment_status')
            ->name('portal.payments.invoice');

        // #409: the Gutschrift, behind the same gate as the invoice it
        // corrects.
        Route::get('/projekte/{project}/zahlungen/{invoice}/gutschrift/{creditNote}', [PaymentController::class, 'creditNote'])
            ->middleware('portal.capability:payment_status')
            ->name('portal.payments.credit-note');
    });
});

// The stored logo, public: a brand mark is not a secret, and it has to
// resolve on the sign-in screen before any contact session exists. Reachable
// during a maintenance closure too, same reasoning as the Impressum below.
Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('portal.branding.logo');
// Q-10: eine Kachel des Reglerrasters. Oeffentlich wie das Logo — der Regler
// steht, bevor irgendjemand angenommen hat.
Route::get('/schaufenster/{index}', [BrandingController::class, 'showcase'])
    ->whereNumber('index')->name('portal.showcase');

// P-05 (#224): the e-mail-change confirmation. Public by necessity — the
// person following it is reading the new inbox, which is not necessarily the
// browser holding the session — and single use, the same rule zugang/{token}
// follows. It signs nobody in; all it does is swap one column.
Route::get('/e-mail-bestaetigen/{token}', [AccountController::class, 'confirmEmail'])
    ->name('portal.account.email.confirm');

// § 5 TMG. Public and reachable regardless of the maintenance closure above —
// see ImpressumController and EnsurePortalMaintenanceClosure's own note.
Route::get('/impressum', ImpressumController::class)->name('portal.impressum');

// Dieselbe Regel für die selbstgeschriebenen Texte: AGB und
// Widerrufsbelehrung stehen beim Vertragsschluss neben dem Häkchen, also
// auch dann, wenn das Portal geschlossen ist.
Route::get('/rechtstexte/{text}', LegalTextController::class)->name('portal.legal-text');

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

    // G-09's heart and G-12's comments, for the portal's own grid (P-08).
    // The api surface keeps its own pair for apps/gallery, which renders
    // from another origin and so cannot reach a session at all — see
    // AssetFavoriteController's doc comment for the localStorage identifier
    // that costs it. Here the guest identity is this session.
    Route::middleware('gallery.gate:view')->group(function () {
        Route::get('/beteiligung', [GalleryEngagementController::class, 'index'])
            ->name('gallery.engagement.index');
        Route::post('/favorit', [GalleryEngagementController::class, 'toggleFavorite'])
            ->middleware('gallery.feature:lists')
            ->name('gallery.favorite.toggle');
        Route::post('/kommentar', [GalleryEngagementController::class, 'storeComment'])
            ->middleware('gallery.feature:comments')
            ->name('gallery.comment.store');
    });

    // The download gates (mockups 11, 12) — reachable only once the view
    // gate above is satisfied, since "download" is a link found inside an
    // already-unlocked gallery, never before it.
    Route::middleware(['gallery.gate:view', 'gallery.feature:downloads'])->group(function () {
        Route::get('/download', [GalleryDownloadController::class, 'show'])->name('gallery.download.show');
        Route::post('/download/pin', [GalleryDownloadController::class, 'storePin'])->name('gallery.download.pin');

        // The "Herunterladen" mail's own link (GalleryZipReady) — a stored,
        // expiring token rather than the bare path show() answers to from
        // ordinary in-gallery navigation, so a forwarded or leaked copy of
        // the mail stops working once GalleryDownloadLink::VALID_FOR_DAYS
        // passes. Same gates as show() apply after the redirect; this only
        // bounds how long the mail's own link works, not the archive.
        Route::get('/download/mail/{token}', [GalleryDownloadController::class, 'openFromMail'])
            ->name('gallery.download.mail');

        // G-03 (#231): "Bilder vorbereiten". Queues BuildGalleryZip for one
        // selection at one size, or does nothing when a fresh archive of the
        // same request already stands.
        Route::post('/download/vorbereiten', [GalleryDownloadController::class, 'prepare'])
            ->name('gallery.download.prepare');

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
