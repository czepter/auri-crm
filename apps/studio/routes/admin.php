<?php

use App\Catalog\Http\Controllers\PackageController;
use App\Contracts\Http\Controllers\ContractController;
use App\Crm\Http\Controllers\AvailabilityController;
use App\Crm\Http\Controllers\CalendarController;
use App\Crm\Http\Controllers\ContactController;
use App\Crm\Http\Controllers\ContactDsgvoController;
use App\Crm\Http\Controllers\ContactSearchController;
use App\Crm\Http\Controllers\CrewController;
use App\Crm\Http\Controllers\FormInstanceController;
use App\Crm\Http\Controllers\FormTemplateController;
use App\Crm\Http\Controllers\InquiryCaptureController;
use App\Crm\Http\Controllers\InternalNoteController;
use App\Crm\Http\Controllers\LeadBoardController;
use App\Crm\Http\Controllers\LeadCaseController;
use App\Crm\Http\Controllers\LeadStageController;
use App\Crm\Http\Controllers\ProjectController;
use App\Crm\Http\Controllers\ProjectSearchController;
use App\Crm\Http\Controllers\ShootController;
use App\Crm\Http\Controllers\ShotListItemController;
use App\Crm\Http\Controllers\TaskController;
use App\Crm\Http\Controllers\TaskTemplateController;
use App\Crm\Http\Controllers\TimelineController;
use App\Documents\Http\Controllers\DocumentTemplateController;
use App\Engagement\Http\Controllers\InboxController;
use App\Engagement\Http\Controllers\ThreadController;
use App\Gallery\Http\Controllers\GalleryAssetController;
use App\Gallery\Http\Controllers\GalleryLightroomExportController;
use App\Gallery\Http\Controllers\GalleryIndexController;
use App\Gallery\Http\Controllers\GalleryPresetController;
use App\Gallery\Http\Controllers\GalleryPublicationController;
use App\Gallery\Http\Controllers\GalleryRecordController;
use App\Gallery\Http\Controllers\GallerySetController;
use App\Http\Controllers\Staff\AbsenceController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\DevLoginController;
use App\Http\Controllers\Staff\InvitationController;
use App\Http\Controllers\Staff\MfaChallengeController;
use App\Http\Controllers\Staff\MfaEnrollmentController;
use App\Http\Controllers\Staff\NotificationController;
use App\Http\Controllers\Staff\OAuthAuthorizationController;
use App\Http\Controllers\Staff\OAuthTokenController;
use App\Http\Controllers\Staff\PasswordResetController;
use App\Http\Controllers\Staff\ProfileController;
use App\Http\Controllers\Staff\SessionController;
use App\Http\Controllers\Staff\SetupController;
use App\Media\Http\Controllers\FailedAssetController;
use App\Media\Http\Controllers\MediaIngestController;
use App\Media\Http\Controllers\MultipartUploadController;
use App\Media\Http\Controllers\UploadPrecheckController;
use App\Payments\Http\Controllers\PaymentInstallmentController;
use App\Payments\Http\Controllers\PaymentPlanController;
use App\Quotes\Http\Controllers\QuoteController;
use App\Reporting\Http\Controllers\ReportController;
use App\Settings\Http\Controllers\GallerySettingsController;
use App\Settings\Http\Controllers\NotificationSettingsController;
use App\Settings\Http\Controllers\NumberRangeController;
use App\Settings\Http\Controllers\PaymentSettingsController;
use App\Settings\Http\Controllers\PortalSettingsController;
use App\Settings\Http\Controllers\StudioClosureController;
use App\Settings\Http\Controllers\StudioOnboardingController;
use App\Settings\Http\Controllers\StudioSettingsController;
use Illuminate\Support\Facades\Route;

// A full authentication bypass for local inspection — no password, no MFA
// challenge. Registered only here, so in every other environment the URL has
// no controller behind it at all. DevLoginController aborts on the same
// condition, and the login page hides its button on it. See that class.
if (app()->environment('local')) {
    Route::post('/dev-login', DevLoginController::class)->name('dev.login');
}

// The calendar subscription feed. Public by necessity — a calendar app cannot
// hold a session — and authenticated by the token in the path, which opens
// exactly one person's own shooting schedule and nothing else. Listed in the
// audience matrix's PUBLIC_ROUTES, so it stays a decision rather than an
// omission.
Route::get('/kalender/{token}/shootings.ics', [CalendarController::class, 'feed'])
    ->name('calendar.feed');

// N-04: the MCP HTTP transport's token exchange. Called by the MCP client
// directly, off-browser — no staff cookie, so no `audience:staff`, and no
// CSRF token, so this path is named in bootstrap/app.php's
// validateCsrfTokens(except:) list. The single-use authorization code and
// its PKCE verifier are the credential; see OAuthTokenController's own doc
// comment for why that is enough without a client_secret.
Route::post('/oauth/token', [OAuthTokenController::class, 'store'])->name('oauth.token');

Route::middleware(['guest:staff', 'setup'])->group(function () {
    Route::get('/einrichtung', [SetupController::class, 'create'])->name('setup');
    Route::post('/einrichtung', [SetupController::class, 'store']);
});

// Staff surface. Session-authenticated, Inertia-rendered, never public.
Route::middleware('guest:staff')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);

    // The challenge runs between password and session: authenticated by the
    // pending-user session key, not by the staff guard.
    Route::get('/auth/mfa', [MfaChallengeController::class, 'create']);
    Route::post('/auth/mfa', [MfaChallengeController::class, 'store']);

    // Reached by someone with no account yet, so necessarily outside the
    // audience — the token in the path is what authorises them, and accepting
    // lands on MFA enrollment like every other staff account.
    Route::get('/einladung/{token}', [InvitationController::class, 'create'])->name('invitations.accept');
    Route::post('/einladung/{token}', [InvitationController::class, 'accept']);

    // Reset proves control of an inbox and nothing more: it signs nobody in
    // and never touches the second factor.
    Route::get('/passwort/vergessen', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/passwort/vergessen', [PasswordResetController::class, 'email']);
    Route::get('/passwort/neu/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/passwort/neu', [PasswordResetController::class, 'update'])->name('password.update');
});

// Neither guest nor staff: the person following it is reading the new inbox,
// which may not be the browser holding the session.
Route::get('/profil/e-mail-bestaetigen/{token}', [ProfileController::class, 'confirmEmail'])
    ->name('profile.email.confirm');

Route::middleware('audience:staff')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);

    Route::get('/auth/mfa/enroll', [MfaEnrollmentController::class, 'create']);
    Route::post('/auth/mfa/confirm', [MfaEnrollmentController::class, 'confirm']);
    Route::get('/auth/mfa/recovery-codes', [MfaEnrollmentController::class, 'recoveryCodes']);

    Route::get('/einrichtung/studio', [StudioOnboardingController::class, 'show'])->name('onboarding');
    Route::post('/einrichtung/studio/abschluss', [StudioOnboardingController::class, 'complete']);

    Route::middleware('onboarding')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        // N-04: the consent screen — approving it hands out a token scoped
        // to this person's own capabilities, same as every other sensitive
        // staff screen below.
        Route::get('/oauth/authorize', [OAuthAuthorizationController::class, 'create'])->name('oauth.authorize');
        Route::post('/oauth/authorize', [OAuthAuthorizationController::class, 'store'])->name('oauth.authorize.store');

        // German paths, English route names: the UI reads in one language and
        // the code reads in the other, and neither has to translate the other.
        //
        // /kontakte/suche stays above any /kontakte/{contact} route, or Laravel
        // binds "suche" as a contact public id and answers 404.
        Route::get('/kontakte/suche', ContactSearchController::class)->name('contacts.search');
        Route::get('/kontakte', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/kontakte/{contact}', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::patch('/kontakte/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        // P-09 (#228). This contact's departures from the studio's portal
        // defaults. Per contact, never per project: an event venue with forty
        // dates a year is set up once, not forty times.
        Route::patch('/kontakte/{contact}/portal', [ContactController::class, 'updatePortalOverrides'])
            ->name('contacts.portal.update');

        // P-09 (#228): mints a single-use, expiring preview link and sends
        // the staff member to it. POST rather than a link in the page: a live
        // credential rendered into a prop is one in a browser history and in
        // a screenshot.
        Route::post('/kontakte/{contact}/portal-vorschau', [ContactController::class, 'previewPortal'])
            ->name('contacts.portal.preview');

        Route::post('/kontakte/{contact}/portal-zugang/widerruf', [ContactController::class, 'revokePortalAccess'])
            ->name('contacts.portal-access.revoke');

        // X-04: DSGVO subject-access export and deletion. Owner-only
        // (Capability::ManageDsgvo) — see ContactDsgvoController's own
        // doc comment.
        Route::get('/kontakte/{contact}/dsgvo/export', [ContactDsgvoController::class, 'export'])
            ->name('contacts.dsgvo.export');
        Route::post('/kontakte/{contact}/dsgvo/loeschen', [ContactDsgvoController::class, 'erase'])
            ->name('contacts.dsgvo.erase');

        Route::get('/verfuegbarkeit', AvailabilityController::class)->name('availability.show');

        // /pakete/neu stays above /pakete/{package} for the same reason
        // /anfragen/neu stays above /anfragen/{lead}/stufe.
        Route::get('/pakete', [PackageController::class, 'index'])->name('packages.index');
        Route::get('/pakete/neu', [PackageController::class, 'create'])->name('packages.create');
        Route::post('/pakete', [PackageController::class, 'store'])->name('packages.store');
        Route::get('/pakete/{package}', [PackageController::class, 'edit'])->name('packages.edit');
        Route::patch('/pakete/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::post('/pakete/{package}/status', [PackageController::class, 'toggle'])->name('packages.toggle');

        // /dokumentvorlagen/neu and /dokumentvorlagen/vorschau stay above
        // /dokumentvorlagen/{documentTemplate} for the same reason /pakete/neu
        // does. T-12 generalises M-01's /vertragsvorlagen into one screen
        // family covering every App\Documents\Enums\DocumentTemplateType.
        Route::get('/dokumentvorlagen', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
        Route::get('/dokumentvorlagen/neu', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
        // T-18: the wizard's template step, and the only writer that takes a
        // starter key rather than a body. Same capability as creating one by
        // hand, because that is what it does.
        Route::post('/dokumentvorlagen/starter', [DocumentTemplateController::class, 'seedStarters'])
            ->name('document-templates.seed');

        Route::post('/dokumentvorlagen', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
        Route::post('/dokumentvorlagen/vorschau', [DocumentTemplateController::class, 'preview'])
            ->name('document-templates.preview');
        Route::get('/dokumentvorlagen/{documentTemplate}', [DocumentTemplateController::class, 'edit'])
            ->name('document-templates.edit');
        Route::patch('/dokumentvorlagen/{documentTemplate}', [DocumentTemplateController::class, 'update'])
            ->name('document-templates.update');

        // T-11's inbox. The in_app transport has written rows since E-07;
        // this is the first screen that reads them.
        Route::get('/benachrichtigungen', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('/benachrichtigungen/gelesen', [NotificationController::class, 'markRead'])
            ->name('notifications.read');

        // The person, not the studio — /einstellungen is the studio's, this
        // is the account whoever is signed in holds. Ungated: everybody with
        // a session has one.
        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profil/passwort', [ProfileController::class, 'updatePassword'])
            ->name('profile.password');
        Route::post('/profil/wiederherstellungscodes', [ProfileController::class, 'regenerateRecoveryCodes'])
            ->name('profile.recovery-codes');
        Route::patch('/profil/benachrichtigungen', [ProfileController::class, 'updateNotifications'])
            ->name('profile.notifications');

        Route::get('/team', [InvitationController::class, 'index'])->name('staff.index');
        Route::post('/team/einladungen', [InvitationController::class, 'store'])->name('staff.invite');
        Route::delete('/team/einladungen/{invitation}', [InvitationController::class, 'destroy'])
            ->name('staff.invitation.destroy');

        // E-12. Everything that arrived and did not thread itself, and the
        // addresses the studio hands out. Rotation posts here but is
        // authorised in RotateInboundSecret rather than by this route — see
        // that action's own doc comment for why the check is not on the
        // screen.
        Route::get('/posteingang', [InboxController::class, 'index'])->name('inbox.index');
        Route::post('/posteingang/{inboundMessage}/zuordnen', [InboxController::class, 'attach'])
            ->name('inbox.attach');
        Route::post('/posteingang/adresse-erneuern', [InboxController::class, 'rotate'])
            ->name('inbox.rotate');

        // P-04 (#223). A client's own thread that names no booking yet — the
        // same tray, the same movement, one level up from a single mail.
        // C-14 (#250): the reply and the "done" mark, from the screen the
        // conversation is read on. Thread-scoped, because a conversation that
        // names no booking still has to be answerable.
        Route::post('/posteingang/unterhaltung/{thread}/antwort', [InboxController::class, 'reply'])
            ->name('inbox.reply');
        Route::post('/posteingang/unterhaltung/{thread}/erledigt', [InboxController::class, 'close'])
            ->name('inbox.close');
        Route::post('/posteingang/unterhaltung/{thread}/zuordnen', [InboxController::class, 'file'])
            ->name('inbox.thread.file');

        // S-13 + M-07. One screen: how the studio is doing, and what is
        // coming in. Gated on ViewAllProjects inside the controller — these
        // are whole-book figures, and a per-role filtered aggregate would
        // mean something different depending who asked.
        Route::get('/auswertungen', ReportController::class)->name('reports.index');

        Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar.index');
        // C-13 (#249): the subscription secret is a credential and belongs in
        // the settings, not as the widest block over the working area.
        Route::get('/einstellungen/kalender', [CalendarController::class, 'subscription'])
            ->name('settings.calendar');
        Route::post('/kalender/abo', [CalendarController::class, 'regenerate'])
            ->name('calendar.regenerate');

        // T-10: per-person unavailability. Open to every role, not gated
        // behind ManageStaff like /team above — AbsenceController itself
        // decides "own row, or ManageCrew" per request, the same 200-not-404
        // shape /verfuegbarkeit above uses for whoever holds ManageLeads.
        Route::get('/abwesenheiten', [AbsenceController::class, 'index'])->name('absences.index');
        Route::post('/abwesenheiten', [AbsenceController::class, 'store'])->name('absences.store');
        Route::patch('/abwesenheiten/{absence}', [AbsenceController::class, 'update'])->name('absences.update');
        Route::delete('/abwesenheiten/{absence}', [AbsenceController::class, 'destroy'])->name('absences.destroy');

        Route::get('/einstellungen', [StudioSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/einstellungen', [StudioSettingsController::class, 'update'])->name('settings.update');
        Route::get('/einstellungen/logo', [StudioSettingsController::class, 'logo'])->name('settings.logo');
        Route::patch('/einstellungen/ust', [StudioSettingsController::class, 'updateVat'])->name('settings.vat.update');
        Route::patch('/einstellungen/dokumente', [StudioSettingsController::class, 'updateDocuments'])->name('settings.documents.update');
        Route::patch('/einstellungen/allgemein', [StudioSettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::patch('/einstellungen/email', [StudioSettingsController::class, 'updateMail'])->name('settings.mail.update');
        Route::patch('/einstellungen/erfassung', [StudioSettingsController::class, 'updateCapture'])->name('settings.capture.update');

        Route::get('/einstellungen/nummernkreise', [NumberRangeController::class, 'index'])
            ->name('number-ranges.index');
        Route::patch('/einstellungen/nummernkreise/{numberRange}', [NumberRangeController::class, 'update'])
            ->name('number-ranges.update');

        Route::get('/einstellungen/verfuegbarkeit', [StudioClosureController::class, 'index'])
            ->name('availability.edit');
        Route::patch('/einstellungen/verfuegbarkeit/dauer', [StudioClosureController::class, 'updateDuration'])
            ->name('availability.duration.update');
        Route::post('/einstellungen/verfuegbarkeit/schliesszeiten', [StudioClosureController::class, 'store'])
            ->name('studio-closures.store');
        Route::patch('/einstellungen/verfuegbarkeit/schliesszeiten/{studioClosure}', [StudioClosureController::class, 'update'])
            ->name('studio-closures.update');
        Route::delete('/einstellungen/verfuegbarkeit/schliesszeiten/{studioClosure}', [StudioClosureController::class, 'destroy'])
            ->name('studio-closures.destroy');

        Route::get('/einstellungen/zahlungen', [PaymentSettingsController::class, 'edit'])
            ->name('settings.payments.edit');
        Route::patch('/einstellungen/zahlungen', [PaymentSettingsController::class, 'update'])
            ->name('settings.payments.update');

        // T-14: its own controller and gate (ManageTasks, not ManageSettings —
        // see TaskTemplateController's doc comment), sitting in the settings
        // section the same way Numbering and Availability do.
        Route::get('/einstellungen/aufgabenvorlagen', [TaskTemplateController::class, 'index'])
            ->name('task-templates.index');
        Route::post('/einstellungen/aufgabenvorlagen', [TaskTemplateController::class, 'store'])
            ->name('task-templates.store');
        Route::patch('/einstellungen/aufgabenvorlagen/{taskTemplate}', [TaskTemplateController::class, 'update'])
            ->name('task-templates.update');
        Route::delete('/einstellungen/aufgabenvorlagen/{taskTemplate}', [TaskTemplateController::class, 'destroy'])
            ->name('task-templates.destroy');

        // E-01: FormTemplate builder — a questionnaire's questions, built
        // once and reused across projects. /neu stays above /{formTemplate}
        // for the same reason /pakete/neu does.
        Route::get('/einstellungen/fragebogen-vorlagen', [FormTemplateController::class, 'index'])
            ->name('form-templates.index');
        Route::get('/einstellungen/fragebogen-vorlagen/neu', [FormTemplateController::class, 'create'])
            ->name('form-templates.create');
        Route::post('/einstellungen/fragebogen-vorlagen', [FormTemplateController::class, 'store'])
            ->name('form-templates.store');
        Route::get('/einstellungen/fragebogen-vorlagen/{formTemplate}', [FormTemplateController::class, 'edit'])
            ->name('form-templates.edit');
        Route::patch('/einstellungen/fragebogen-vorlagen/{formTemplate}', [FormTemplateController::class, 'update'])
            ->name('form-templates.update');
        Route::delete('/einstellungen/fragebogen-vorlagen/{formTemplate}', [FormTemplateController::class, 'destroy'])
            ->name('form-templates.destroy');

        Route::get('/einstellungen/portal', [PortalSettingsController::class, 'edit'])
            ->name('settings.portal.edit');
        Route::patch('/einstellungen/portal', [PortalSettingsController::class, 'update'])
            ->name('settings.portal.update');

        Route::get('/einstellungen/galerien', [GallerySettingsController::class, 'edit'])
            ->name('settings.galleries.edit');
        Route::patch('/einstellungen/galerien', [GallerySettingsController::class, 'update'])
            ->name('settings.galleries.update');

        Route::get('/einstellungen/galerie-vorlagen', [GalleryPresetController::class, 'index'])
            ->name('gallery-presets.index');
        Route::post('/einstellungen/galerie-vorlagen', [GalleryPresetController::class, 'store'])
            ->name('gallery-presets.store');
        Route::patch('/einstellungen/galerie-vorlagen/{galleryPreset}', [GalleryPresetController::class, 'update'])
            ->name('gallery-presets.update');
        Route::delete('/einstellungen/galerie-vorlagen/{galleryPreset}', [GalleryPresetController::class, 'destroy'])
            ->name('gallery-presets.destroy');

        // T-11: quiet hours only — see NotificationSettingsController's own
        // doc comment for why no per-event toggle grid ships in this route
        // group yet.
        Route::get('/einstellungen/benachrichtigungen', [NotificationSettingsController::class, 'edit'])
            ->name('settings.notifications.edit');
        Route::patch('/einstellungen/benachrichtigungen', [NotificationSettingsController::class, 'update'])
            ->name('settings.notifications.update');

        // /anfragen/neu stays above /anfragen/{lead}/stufe for the same reason
        // /kontakte/suche stays above /kontakte/{contact}.
        Route::get('/anfragen/neu', [InquiryCaptureController::class, 'create'])->name('leads.create');
        Route::get('/anfragen', [LeadBoardController::class, '__invoke'])->name('leads.index');
        // C-07 (#241): the view the user last chose, kept on the user.
        Route::post('/anfragen/ansicht', [LeadBoardController::class, 'chooseView'])
            ->name('leads.view');
        Route::post('/anfragen', [InquiryCaptureController::class, 'store'])->name('leads.store');
        // C-08 (#243): one inquiry beside the queue it came from. Below
        // /anfragen/neu and /anfragen/ansicht, which would otherwise bind as
        // a lead id.
        Route::get('/anfragen/{lead}', [LeadCaseController::class, 'show'])->name('leads.show');
        // C-10 (#245): editing is a state of the view, so it posts back to
        // the same case rather than to a screen of its own.
        Route::patch('/anfragen/{lead}', [LeadCaseController::class, 'update'])->name('leads.update');
        Route::post('/anfragen/{lead}/stufe', [LeadStageController::class, 'update'])
            ->name('leads.stage.update');

        // C-02 (#236): the list behind the sidebar's Projekte entry. Its own
        // path, not /auftraege: a Job is what a booked project becomes, and
        // the list covers every project whether or not one exists yet.
        Route::get('/projekte', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projekte/suche', ProjectSearchController::class)->name('projects.search');

        // The project screen. Shoot and crew mutations hang off /shootings
        // rather than under the project, because the shoot's public id already
        // identifies it and nesting would let the two disagree.
        Route::get('/auftraege/{project}', [ProjectController::class, 'show'])->name('projects.show');
        // C-12 (#248): editing is a state of the screen, so it posts back to
        // the same record.
        Route::patch('/auftraege/{project}', [ProjectController::class, 'update'])
            ->name('projects.update');
        Route::get('/auftraege/{project}/shootings', [ProjectController::class, 'shoots'])
            ->name('projects.shoots');
        Route::post('/auftraege/{project}/buchung', [ProjectController::class, 'book'])
            ->name('projects.book');
        Route::post('/auftraege/{project}/portal-sichtbarkeit', [ProjectController::class, 'togglePortalVisibility'])
            ->name('projects.portal-visibility.toggle');

        // E-06. The client is the only one who can start a thread (see
        // StartThread, reached from routes/portal.php) — this GET reads
        // whatever the client has already opened, and the POST beside it is
        // staff's reply on it, never an origination of its own.
        Route::get('/auftraege/{project}/nachrichten', [ProjectController::class, 'messages'])
            ->name('projects.messages');
        Route::post('/auftraege/{project}/nachrichten', [ThreadController::class, 'reply'])
            ->name('threads.reply');
        // E-13: cancel one that has not gone out yet.
        Route::delete('/auftraege/{project}/nachrichten/{message}', [ThreadController::class, 'cancel'])
            ->name('threads.cancel');

        Route::get('/auftraege/{project}/angebote', [ProjectController::class, 'quotes'])
            ->name('projects.quotes');

        Route::post('/auftraege/{project}/angebote', [QuoteController::class, 'store'])
            ->name('quotes.store');
        Route::patch('/angebote/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
        Route::post('/angebote/{quote}/versenden', [QuoteController::class, 'send'])->name('quotes.send');

        // E-02: a project's own questionnaires, seeded from a FormTemplate
        // or started blank, then personalised freely — see FormInstance's
        // own doc comment.
        Route::get('/auftraege/{project}/frageboegen', [ProjectController::class, 'forms'])
            ->name('projects.forms');
        Route::post('/auftraege/{project}/frageboegen', [FormInstanceController::class, 'store'])
            ->name('form-instances.store');
        Route::patch('/frageboegen/{formInstance}', [FormInstanceController::class, 'update'])
            ->name('form-instances.update');
        // E-03: recording the answer to one question — kept off /frageboegen
        // itself, since answering a field never touches the field set update()
        // wholesale-replaces above.
        // P-03 (#222). Sharing is what makes a questionnaire fillable in the
        // portal; without it the client's screen 404s, same as an internal
        // checklist on the same table should.
        Route::post('/frageboegen/{formInstance}/freigeben', [FormInstanceController::class, 'share'])
            ->name('form-instances.share');

        Route::patch('/frageboegen-felder/{formInstanceField}/antwort', [FormInstanceController::class, 'answer'])
            ->name('form-instance-fields.answer');

        Route::get('/auftraege/{project}/vertrag', [ContractController::class, 'show'])
            ->name('projects.contract');
        Route::post('/auftraege/{project}/vertraege', [ContractController::class, 'store'])
            ->name('contracts.store');
        Route::get('/vertraege/{contract}/pdf', [ContractController::class, 'download'])
            ->name('contracts.download');

        // G-02: app-proxied multipart ingest into a gallery's quarantine
        // prefix. galleries.uploads.* is a JSON API for the browser's own
        // chunked uploader (resources/js/admin/lib/multipartUpload.ts), not
        // Inertia — see MultipartUploadController.
        // C-19 (#255): the gallery as a record with five tabs, not a data
        // sheet with switches. Above /galerien/{gallery}/medien, which stays
        // the ingest screen it always was.
        Route::get('/galerien', [GalleryIndexController::class, 'index'])->name('galleries.index');
        // /galerien/neu bleibt über /galerien/{gallery}, aus demselben Grund
        // wie /pakete/neu über /pakete/{package}.
        Route::get('/galerien/neu', [GalleryIndexController::class, 'create'])->name('galleries.create');
        Route::post('/galerien', [GalleryIndexController::class, 'store'])->name('galleries.store');
        Route::get('/galerien/{gallery}', [GalleryRecordController::class, 'show'])
            ->name('galleries.show');
        Route::patch('/galerien/{gallery}', [GalleryRecordController::class, 'update'])
            ->name('galleries.update');
        Route::post('/galerien/{gallery}/vorlage', [GalleryPresetController::class, 'storeFromGallery'])
            ->name('galleries.preset.store');
        Route::post('/galerien/{gallery}/sets', [GallerySetController::class, 'store'])
            ->name('galleries.sets.store');
        // Above /sets/{gallerySet}, sonst schluckt die Bindung "reihenfolge"
        // als public_id.
        Route::patch('/galerien/{gallery}/sets/reihenfolge', [GallerySetController::class, 'reorder'])
            ->name('galleries.sets.reorder');
        Route::patch('/galerien/{gallery}/sets/{gallerySet}', [GallerySetController::class, 'update'])
            ->name('galleries.sets.update');
        Route::delete('/galerien/{gallery}/sets/{gallerySet}', [GallerySetController::class, 'destroy'])
            ->name('galleries.sets.destroy');
        Route::get('/galerien/{gallery}/medien', [MediaIngestController::class, 'show'])
            ->name('galleries.media');
        Route::post('/galerien/{gallery}/uploads', [MultipartUploadController::class, 'store'])
            ->name('galleries.uploads.store');
        Route::put('/uploads/{multipartUpload}/teile/{partNumber}', [MultipartUploadController::class, 'uploadPart'])
            ->whereNumber('partNumber')
            ->name('galleries.uploads.parts.store');
        Route::post('/uploads/{multipartUpload}/abschluss', [MultipartUploadController::class, 'complete'])
            ->name('galleries.uploads.complete');
        Route::delete('/uploads/{multipartUpload}', [MultipartUploadController::class, 'destroy'])
            ->name('galleries.uploads.destroy');

        // G-12: the studio side of proofing — every favorited asset in the
        // gallery's current version, as a Lightroom-importable filename
        // list. No admin gallery-detail screen exists yet to hang a button
        // on (see the controller's own doc comment), so this is reachable by
        // URL only until one does.
        Route::get('/galerien/{gallery}/lightroom-export', GalleryLightroomExportController::class)
            ->name('galleries.lightroom-export');

        // G-04: the ingest screen's "needs attention" tray — re-runs G-03's
        // ingest pipeline for one Failed asset. Inertia (a redirect back),
        // not the JSON API above: this reloads the same ingest screen's
        // props rather than driving client-side upload state.
        Route::post('/medien/{asset}/wiederholen', [FailedAssetController::class, 'retry'])
            ->name('assets.retry');

        Route::post('/galerien/{gallery}/bilder/verschieben', [GalleryAssetController::class, 'move'])
            ->name('galleries.assets.move');
        Route::delete('/galerien/{gallery}/bilder', [GalleryAssetController::class, 'remove'])
            ->name('galleries.assets.remove');
        Route::get('/galerien/{gallery}/bilder/{asset}/original', [GalleryAssetController::class, 'original'])
            ->name('galleries.assets.original');
        Route::patch('/galerien/{gallery}/titelbild', [GalleryAssetController::class, 'cover'])
            ->name('galleries.cover');

        Route::post('/galerien/{gallery}/uploads/pruefen', UploadPrecheckController::class)
            ->name('galleries.uploads.precheck');

        Route::post('/galerien/{gallery}/veroeffentlichen', [GalleryPublicationController::class, 'store'])
            ->name('galleries.publish');
        Route::delete('/galerien/{gallery}/veroeffentlichung', [GalleryPublicationController::class, 'destroy'])
            ->name('galleries.withdraw');

        // No GET here: the Zahlungen card lives on the project overview
        // itself (ProjectController::show), not a tab of its own — see the
        // note in ProjectHeaderData::fromProject.
        Route::post('/auftraege/{project}/zahlungsplan', [PaymentPlanController::class, 'store'])
            ->name('payment-plans.store');
        Route::patch('/zahlungsplaene/{paymentPlan}', [PaymentPlanController::class, 'update'])
            ->name('payment-plans.update');

        // T-07's staff action: date, method and reference, recorded once —
        // never a status flipped on its own. See MarkInstallmentPaid.
        Route::post('/raten/{installment}/bezahlt', [PaymentInstallmentController::class, 'markPaid'])
            ->name('payment-installments.mark-paid');

        // M-06's "Fällig stellen" — the row S-09 deferred, because at the
        // time no invoice state existed for it to move into.
        Route::post('/raten/{installment}/rechnung', [PaymentInstallmentController::class, 'issueInvoice'])
            ->name('payment-installments.issue-invoice');
        Route::get('/raten/{installment}/rechnung', [PaymentInstallmentController::class, 'downloadInvoice'])
            ->name('payment-installments.invoice');

        Route::post('/auftraege/{project}/shootings', [ShootController::class, 'store'])
            ->name('shoots.store');
        Route::post('/shootings/{shoot}/status', [ShootController::class, 'advance'])
            ->name('shoots.advance');
        Route::post('/shootings/{shoot}/absage', [ShootController::class, 'cancel'])
            ->name('shoots.cancel');
        Route::post('/shootings/{shoot}/haupttermin', [ShootController::class, 'designatePrimary'])
            ->name('shoots.primary');

        Route::post('/shootings/{shoot}/crew', [CrewController::class, 'store'])->name('crew.store');
        Route::delete('/crew/{assignment}', [CrewController::class, 'destroy'])->name('crew.destroy');

        // E-04: the day-of schedule, shoot-scoped for the same reason the
        // wedding-day timeline is not the engagement session's. Export routes
        // sit beside the CRUD rather than under a separate controller — both
        // read the same `timeline` relation, just rendered differently.
        Route::post('/shootings/{shoot}/ablauf', [TimelineController::class, 'store'])
            ->name('timeline.store');
        Route::delete('/ablauf/{entry}', [TimelineController::class, 'destroy'])
            ->name('timeline.destroy');
        Route::get('/shootings/{shoot}/ablauf.pdf', [TimelineController::class, 'pdf'])
            ->name('timeline.pdf');
        Route::get('/shootings/{shoot}/ablauf.ics', [TimelineController::class, 'ics'])
            ->name('timeline.ics');

        Route::post('/shootings/{shoot}/checkliste', [ShotListItemController::class, 'store'])
            ->name('shot-list-items.store');
        Route::post('/checkliste/{item}/erledigt', [ShotListItemController::class, 'toggle'])
            ->name('shot-list-items.toggle');
        Route::delete('/checkliste/{item}', [ShotListItemController::class, 'destroy'])
            ->name('shot-list-items.destroy');

        Route::post('/auftraege/{project}/aufgaben', [TaskController::class, 'store'])
            ->name('tasks.store');
        Route::post('/aufgaben/{task}/erledigt', [TaskController::class, 'complete'])
            ->name('tasks.complete');
        // S-12: what this task waits on. Its own route rather than a field on
        // completion, because changing a dependency and finishing the work
        // are different decisions taken at different moments.
        Route::post('/aufgaben/{task}/abhaengigkeit', [TaskController::class, 'depend'])
            ->name('tasks.depend');
        Route::delete('/aufgaben/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        Route::post('/auftraege/{project}/notizen', [InternalNoteController::class, 'store'])
            ->name('notes.store');
        Route::patch('/notizen/{note}', [InternalNoteController::class, 'update'])->name('notes.update');
        Route::delete('/notizen/{note}', [InternalNoteController::class, 'destroy'])
            ->name('notes.destroy');
    });
});
