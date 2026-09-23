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
use App\Integration\Http\Controllers\CrewController;
use App\Integration\Http\Controllers\CrmReadController;
use App\Integration\Http\Controllers\FormInstanceController;
use App\Integration\Http\Controllers\GalleryController;
use App\Integration\Http\Controllers\InboxController;
use App\Integration\Http\Controllers\InternalNoteController;
use App\Integration\Http\Controllers\LeadController;
use App\Integration\Http\Controllers\MessageController;
use App\Integration\Http\Controllers\PackageController;
use App\Integration\Http\Controllers\PaymentController;
use App\Integration\Http\Controllers\ProjectContactController;
use App\Integration\Http\Controllers\ProjectController;
use App\Integration\Http\Controllers\ProjectFieldController;
use App\Integration\Http\Controllers\QuoteActionController;
use App\Integration\Http\Controllers\QuoteController;
use App\Integration\Http\Controllers\ServiceSuggestionController;
use App\Integration\Http\Controllers\ShootController;
use App\Integration\Http\Controllers\ShotListItemController;
use App\Integration\Http\Controllers\StudioReadController;
use App\Integration\Http\Controllers\TaskController;
use App\Integration\Http\Controllers\ThreadController;
use App\Integration\Http\Controllers\TimelineController;
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

// I-02: read-only pages over the CRM core for an ApiToken caller (I-01) —
// see CrmReadController's own doc comment for the cursor-pagination and
// `since=` shape every one of these shares. Each scope reuses the exact
// Capability that already gates the matching admin screen (ContactPolicy,
// LeadPolicy, ProjectPolicy) rather than inventing an API-only vocabulary —
// a token can never read something no staff role could. Shoots have no
// policy of their own; they hang off a project (ShootPolicy already
// delegates to ProjectPolicy), so they reuse ViewAllProjects, the capability
// that already means "see every project" rather than only the ones you are
// crewed on.
Route::middleware('audience:api')->group(function () {
    Route::middleware('scope:'.Capability::ManageContacts->value)
        ->get('/contacts', [CrmReadController::class, 'contacts'])->name('api.contacts.index');
    Route::middleware('scope:'.Capability::ViewAllProjects->value)
        ->get('/projects', [CrmReadController::class, 'projects'])->name('api.projects.index');
    Route::middleware('scope:'.Capability::ManageLeads->value)
        ->get('/leads', [CrmReadController::class, 'leads'])->name('api.leads.index');
    Route::middleware('scope:'.Capability::ViewAllProjects->value)
        ->get('/shoots', [CrmReadController::class, 'shoots'])->name('api.shoots.index');
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
    // ManageNotes, not ViewAllProjects: notes stay out of the project read.
    Route::middleware('scope:'.Capability::ManageNotes->value)
        ->get('/projects/{project:public_id}/notes', [InternalNoteController::class, 'index'])
        ->name('api.projects.notes.index');
    Route::middleware('scope:'.Capability::ManageLeads->value)
        ->get('/pipeline-snapshot', [CrmReadController::class, 'pipelineSnapshot'])
        ->name('api.pipeline-snapshot');
    // #402: ManageContracts, the capability managePaymentPlan reuses — the
    // same scope covers the write routes below.
    Route::middleware('scope:'.Capability::ManageContracts->value)
        ->get('/projects/{project:public_id}/money', [CrmReadController::class, 'money'])
        ->name('api.projects.money');
});

// I-03: the api surface's first write endpoints — a workflow runner with a
// retry policy is the caller, so every one of these carries `idempotent`
// (App\Http\Middleware\EnsureIdempotentWrite) after `scope:`, in that order:
// a caller with the wrong scope must never even claim an idempotency key. All
// four reuse the exact Action or model call the matching admin-surface
// controller already makes — see each controller's own doc comment for which
// one and why an ApiToken can still make it without a staff User to attribute
// it to.
Route::middleware(['audience:api', 'scope:'.Capability::ManageContacts->value, 'idempotent'])->group(function () {
    Route::post('/contacts', [ContactController::class, 'store'])->name('api.contacts.store');
    Route::patch('/contacts/{contact:public_id}', [ContactController::class, 'update'])->name('api.contacts.update');
});

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

Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->patch('/leads/{lead:public_id}', [LeadController::class, 'update'])
    ->name('api.leads.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->patch('/projects/{project:public_id}', [ProjectController::class, 'update'])
    ->name('api.projects.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->delete('/projects/{project:public_id}/contacts/{contact:public_id}', [ProjectContactController::class, 'destroy'])
    ->name('api.projects.contacts.destroy');

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
    ->patch('/shoots/{shoot:public_id}', [ShootController::class, 'update'])
    ->name('api.shoots.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->delete('/shoots/{shoot:public_id}', [ShootController::class, 'destroy'])
    ->name('api.shoots.destroy');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/shoots/{shoot:public_id}/primary', [ShootController::class, 'designatePrimary'])
    ->name('api.shoots.primary');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/shoots/{shoot:public_id}/status', [ShootController::class, 'advance'])
    ->name('api.shoots.status.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/shoots/{shoot:public_id}/cancel', [ShootController::class, 'cancel'])
    ->name('api.shoots.cancel');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])
    ->post('/shoots/{shoot:public_id}/uncancel', [ShootController::class, 'uncancel'])
    ->name('api.shoots.uncancel');

// The catalogue. Reading it is ManageQuotes as well as ManagePackages — a
// token that may write quotes has to see what a quote can start from —, while
// writing one is ManagePackages alone, the capability PackagePolicy routes to.
Route::middleware(['audience:api', 'scope:'.Capability::ManageQuotes->value.','.Capability::ManagePackages->value])
    ->get('/packages', [PackageController::class, 'index'])
    ->name('api.packages.index');

Route::middleware(['audience:api', 'scope:'.Capability::ManagePackages->value, 'idempotent'])->group(function () {
    Route::post('/packages', [PackageController::class, 'store'])->name('api.packages.store');
    Route::patch('/packages/{package:public_id}', [PackageController::class, 'update'])->name('api.packages.update');
    Route::delete('/packages/{package:public_id}', [PackageController::class, 'destroy'])->name('api.packages.destroy');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManageQuotes->value, 'idempotent'])->group(function () {
    Route::post('/projects/{project:public_id}/quotes', [QuoteController::class, 'store'])->name('api.projects.quotes.store');
    Route::patch('/quotes/{quote:public_id}', [QuoteController::class, 'update'])->name('api.quotes.update');
    Route::post('/quotes/{quote:public_id}/send', [QuoteController::class, 'send'])->name('api.quotes.send');
    Route::post('/quotes/{quote:public_id}/revise', [QuoteController::class, 'revise'])->name('api.quotes.revise');
    Route::post('/quotes/{quote:public_id}/accept', [QuoteController::class, 'accept'])->name('api.quotes.accept');
});

// #403: the admin-only quote actions (addendum, duplicate, delete a draft,
// mark sent by self, adopt the recipient, save as a package) — same
// ManageQuotes scope as the group above, since each is a write UpdateQuote's
// group already gates.
Route::middleware(['audience:api', 'scope:'.Capability::ManageQuotes->value, 'idempotent'])->group(function () {
    Route::post('/quotes/{quote:public_id}/addendum', [QuoteActionController::class, 'addendum'])->name('api.quotes.addendum');
    Route::post('/quotes/{quote:public_id}/duplicate', [QuoteActionController::class, 'duplicate'])->name('api.quotes.duplicate');
    Route::delete('/quotes/{quote:public_id}', [QuoteActionController::class, 'destroy'])->name('api.quotes.destroy');
    Route::post('/quotes/{quote:public_id}/mark-sent', [QuoteActionController::class, 'markSent'])->name('api.quotes.mark-sent');
    Route::post('/quotes/{quote:public_id}/adopt-recipient', [QuoteActionController::class, 'adoptRecipient'])->name('api.quotes.adopt-recipient');
    Route::post('/quotes/{quote:public_id}/save-as-package', [QuoteActionController::class, 'saveAsPackage'])->name('api.quotes.save-as-package');
});

// #403: personal service recommendations (App\Catalog\Http\Controllers\
// ServiceSuggestionController is the admin twin) — ManageContacts, the
// capability the contact page they live on already requires.
Route::middleware(['audience:api', 'scope:'.Capability::ManageContacts->value])
    ->get('/contacts/{contact:public_id}/service-suggestions', [ServiceSuggestionController::class, 'index'])
    ->name('api.contacts.service-suggestions.index');

Route::middleware(['audience:api', 'scope:'.Capability::ManageContacts->value, 'idempotent'])->group(function () {
    Route::post('/contacts/{contact:public_id}/service-suggestions', [ServiceSuggestionController::class, 'store'])
        ->name('api.contacts.service-suggestions.store');
    Route::delete('/service-suggestions/{serviceSuggestion:public_id}', [ServiceSuggestionController::class, 'destroy'])
        ->name('api.service-suggestions.destroy');
});

// Taking an import back — see ProjectController. ManageLeads, the scope that
// created the project through leads.store: whoever may capture a run may undo
// it, and no narrower capability exists for "may delete" (I-01).
Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->delete('/projects/{project:public_id}', [ProjectController::class, 'destroy'])
    ->name('api.projects.destroy');

Route::middleware(['audience:api', 'scope:'.Capability::ManageNotes->value, 'idempotent'])
    ->post('/projects/{project:public_id}/notes', [InternalNoteController::class, 'store'])
    ->name('api.projects.notes.store');

Route::middleware(['audience:api', 'scope:'.Capability::ManageNotes->value, 'idempotent'])
    ->patch('/notes/{note:public_id}', [InternalNoteController::class, 'update'])
    ->name('api.notes.update');

Route::middleware(['audience:api', 'scope:'.Capability::ManageNotes->value, 'idempotent'])
    ->delete('/notes/{note:public_id}', [InternalNoteController::class, 'destroy'])
    ->name('api.notes.destroy');

// ManageMessages, the capability the admin reply already requires.
Route::middleware(['audience:api', 'scope:'.Capability::ManageMessages->value, 'idempotent'])
    ->post('/projects/{project:public_id}/messages', [MessageController::class, 'store'])
    ->name('api.projects.messages.store');

// #404: the send half of E-06/E-13, through the same PostMessage the admin
// reply and cancel use — see ThreadController (Integration) for why this
// needs a staff actor where messages.store above does not.
Route::middleware(['audience:api', 'scope:'.Capability::ManageMessages->value, 'idempotent'])
    ->post('/projects/{project:public_id}/messages/send', [ThreadController::class, 'reply'])
    ->name('api.projects.messages.send');

Route::middleware(['audience:api', 'scope:'.Capability::ManageMessages->value, 'idempotent'])
    ->delete('/messages/{message:public_id}', [ThreadController::class, 'cancel'])
    ->name('api.messages.cancel');

// #404: E-12/P-04's inbox actions, through the same Actions the admin
// Posteingang uses — see InboxController (Integration).
Route::middleware(['audience:api', 'scope:'.Capability::ManageMessages->value])
    ->get('/inbox/threads', [InboxController::class, 'index'])
    ->name('api.inbox.threads.index');

Route::middleware(['audience:api', 'scope:'.Capability::ManageMessages->value, 'idempotent'])->group(function () {
    Route::post('/inbox/threads/{thread:public_id}/reply', [InboxController::class, 'reply'])
        ->name('api.inbox.threads.reply');
    Route::post('/inbox/threads/{thread:public_id}/close', [InboxController::class, 'close'])
        ->name('api.inbox.threads.close');
    Route::post('/inbox/threads/{thread:public_id}/file', [InboxController::class, 'file'])
        ->name('api.inbox.threads.file');
    Route::post('/inbox/messages/{inboundMessage:public_id}/attach', [InboxController::class, 'attach'])
        ->name('api.inbox.messages.attach');
});

// ManageLeads, the scope leads.store already attaches contacts under.
Route::middleware(['audience:api', 'scope:'.Capability::ManageLeads->value, 'idempotent'])
    ->post('/projects/{project:public_id}/contacts', [ProjectContactController::class, 'store'])
    ->name('api.projects.contacts.store');

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

// #401: booking, completion and reopening, mirroring App\Crm\Http\
// Controllers\ProjectController's own book()/complete()/reopen() — same
// BookProject and CompleteProject actions, so a token can commit or close a
// project exactly as staff do from the project screen.
Route::middleware(['audience:api', 'scope:'.Capability::BookProject->value, 'idempotent'])
    ->post('/projects/{project:public_id}/book', [ProjectController::class, 'book'])
    ->name('api.projects.book');

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])->group(function () {
    Route::post('/projects/{project:public_id}/complete', [ProjectController::class, 'complete'])
        ->name('api.projects.complete');
    Route::delete('/projects/{project:public_id}/complete', [ProjectController::class, 'reopen'])
        ->name('api.projects.reopen');
});

// #401: tasks, mirroring App\Crm\Http\Controllers\TaskController. ManageTasks
// throughout, the capability that route already authorizes against.
Route::middleware(['audience:api', 'scope:'.Capability::ManageTasks->value])
    ->get('/projects/{project:public_id}/tasks', [TaskController::class, 'index'])
    ->name('api.projects.tasks.index');

Route::middleware(['audience:api', 'scope:'.Capability::ManageTasks->value, 'idempotent'])->group(function () {
    Route::post('/projects/{project:public_id}/tasks', [TaskController::class, 'store'])
        ->name('api.projects.tasks.store');
    Route::post('/tasks/{task:public_id}/complete', [TaskController::class, 'complete'])
        ->name('api.tasks.complete');
    Route::post('/tasks/{task:public_id}/reopen', [TaskController::class, 'reopen'])
        ->name('api.tasks.reopen');
    Route::post('/tasks/{task:public_id}/assign', [TaskController::class, 'assign'])
        ->name('api.tasks.assign');
    Route::post('/tasks/{task:public_id}/depend', [TaskController::class, 'depend'])
        ->name('api.tasks.depend');
    Route::delete('/tasks/{task:public_id}', [TaskController::class, 'destroy'])
        ->name('api.tasks.destroy');
});

// #402: payments, ManageContracts — see ProjectPolicy::managePaymentPlan().
Route::middleware(['audience:api', 'scope:'.Capability::ManageContracts->value, 'idempotent'])->group(function () {
    Route::post('/projects/{project:public_id}/payment-plan', [PaymentController::class, 'store'])
        ->name('api.projects.payment-plan.store');
    Route::patch('/payment-plans/{paymentPlan:public_id}', [PaymentController::class, 'update'])
        ->name('api.payment-plans.update');
    Route::post('/payment-installments/{installment:public_id}/paid', [PaymentController::class, 'markPaid'])
        ->name('api.payment-installments.paid');
    Route::post('/payment-installments/{installment:public_id}/invoice', [PaymentController::class, 'issueInvoice'])
        ->name('api.payment-installments.invoice');
});

// #405: the token surface over what GalleryIndexController, GalleryRecord
// Controller, GalleryPublicationController, GalleryPrereleaseController,
// GalleryPreviewController and GalleryLightroomExportController already do
// in the admin. Item routes live under /gallery-records rather than
// /galleries/{gallery}: that exact pattern is already the guest viewer's
// GalleryReadController above (no audience guard, `gallery.pass` further
// down), and a second route on the same method+URI never matches — only
// the first one registered would ever run. `/galleries` alone (list,
// create) has no such guest route to collide with.
//
// Record edits (get, favorites, create, update) reuse ManageShoots, the
// same capability GalleryPolicy::manageMedia already routes reading and
// editing a gallery's draft through. Publishing, withdrawing, pre-release
// and a preview link reuse ManagePortalAccess, the capability GalleryPolicy
// ::publish already routes "is this gallery presentable outside the
// studio" through.
Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value])->group(function () {
    Route::get('/galleries', [GalleryController::class, 'index'])->name('api.galleries.index');
    Route::get('/gallery-records/{gallery:public_id}', [GalleryController::class, 'show'])->name('api.gallery-records.show');
    Route::get('/gallery-records/{gallery:public_id}/favorites', [GalleryController::class, 'favorites'])->name('api.gallery-records.favorites');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])->group(function () {
    Route::post('/galleries', [GalleryController::class, 'store'])->name('api.galleries.store');
    Route::patch('/gallery-records/{gallery:public_id}', [GalleryController::class, 'update'])->name('api.gallery-records.update');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManagePortalAccess->value, 'idempotent'])->group(function () {
    Route::post('/gallery-records/{gallery:public_id}/publish', [GalleryController::class, 'publish'])->name('api.gallery-records.publish');
    Route::post('/gallery-records/{gallery:public_id}/withdraw', [GalleryController::class, 'withdraw'])->name('api.gallery-records.withdraw');
    Route::post('/gallery-records/{gallery:public_id}/prerelease/start', [GalleryController::class, 'startPrerelease'])->name('api.gallery-records.prerelease.start');
    Route::post('/gallery-records/{gallery:public_id}/prerelease/stop', [GalleryController::class, 'stopPrerelease'])->name('api.gallery-records.prerelease.stop');
    Route::post('/gallery-records/{gallery:public_id}/preview-link', [GalleryController::class, 'previewLink'])->name('api.gallery-records.preview-link');
});

// #406: the studio's own settings, closures, number ranges and staff —
// reads a token needs to reason about the studio, not act as it.
Route::middleware(['audience:api', 'scope:'.Capability::ManageSettings->value])->group(function () {
    Route::get('/studio-settings', [StudioReadController::class, 'settings'])->name('api.studio-settings.show');
    Route::get('/studio-closures', [StudioReadController::class, 'closures'])->name('api.studio-closures.index');
    Route::get('/number-ranges', [StudioReadController::class, 'numberRanges'])->name('api.number-ranges.index');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManageCrew->value])->group(function () {
    Route::get('/staff', [CrewController::class, 'staff'])->name('api.staff.index');
    Route::get('/shoots/{shoot:public_id}/crew', [CrewController::class, 'index'])->name('api.shoots.crew.index');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManageCrew->value, 'idempotent'])->group(function () {
    Route::post('/shoots/{shoot:public_id}/crew', [CrewController::class, 'store'])->name('api.shoots.crew.store');
    Route::delete('/crew/{assignment:public_id}', [CrewController::class, 'destroy'])->name('api.crew.destroy');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value])->group(function () {
    Route::get('/shoots/{shoot:public_id}/timeline', [TimelineController::class, 'index'])->name('api.shoots.timeline.index');
    Route::get('/shoots/{shoot:public_id}/shot-list', [ShotListItemController::class, 'index'])->name('api.shoots.shot-list.index');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManageShoots->value, 'idempotent'])->group(function () {
    Route::post('/shoots/{shoot:public_id}/timeline', [TimelineController::class, 'store'])->name('api.shoots.timeline.store');
    Route::delete('/timeline/{entry:public_id}', [TimelineController::class, 'destroy'])->name('api.timeline.destroy');
    Route::post('/shoots/{shoot:public_id}/shot-list', [ShotListItemController::class, 'store'])->name('api.shoots.shot-list.store');
    Route::post('/shot-list/{item:public_id}/check', [ShotListItemController::class, 'check'])->name('api.shot-list.check');
    Route::delete('/shot-list/{item:public_id}', [ShotListItemController::class, 'destroy'])->name('api.shot-list.destroy');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManageFormTemplates->value])
    ->get('/projects/{project:public_id}/forms', [FormInstanceController::class, 'index'])
    ->name('api.projects.forms.index');

Route::middleware(['audience:api', 'scope:'.Capability::ManageFormTemplates->value, 'idempotent'])->group(function () {
    Route::post('/projects/{project:public_id}/forms', [FormInstanceController::class, 'store'])->name('api.projects.forms.store');
    Route::patch('/forms/{formInstance:public_id}', [FormInstanceController::class, 'update'])->name('api.forms.update');
    Route::post('/forms/{formInstance:public_id}/share', [FormInstanceController::class, 'share'])->name('api.forms.share');
    Route::post('/form-fields/{formInstanceField:public_id}/answer', [FormInstanceController::class, 'answer'])->name('api.form-fields.answer');
});

Route::middleware(['audience:api', 'scope:'.Capability::ManagePortalAccess->value, 'idempotent'])
    ->post('/projects/{project:public_id}/portal-visibility', [ProjectController::class, 'setPortalVisibility'])
    ->name('api.projects.portal-visibility.update');
