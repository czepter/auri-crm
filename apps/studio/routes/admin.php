<?php

use App\Catalog\Http\Controllers\PackageController;
use App\Contracts\Http\Controllers\ContractController;
use App\Contracts\Http\Controllers\ContractTemplateController;
use App\Crm\Http\Controllers\AvailabilityController;
use App\Crm\Http\Controllers\CalendarController;
use App\Crm\Http\Controllers\ContactController;
use App\Crm\Http\Controllers\ContactSearchController;
use App\Crm\Http\Controllers\CrewController;
use App\Crm\Http\Controllers\InquiryCaptureController;
use App\Crm\Http\Controllers\InternalNoteController;
use App\Crm\Http\Controllers\LeadBoardController;
use App\Crm\Http\Controllers\LeadStageController;
use App\Crm\Http\Controllers\ProjectController;
use App\Crm\Http\Controllers\ShootController;
use App\Crm\Http\Controllers\TaskController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\DevLoginController;
use App\Http\Controllers\Staff\InvitationController;
use App\Http\Controllers\Staff\MfaChallengeController;
use App\Http\Controllers\Staff\MfaEnrollmentController;
use App\Http\Controllers\Staff\PasswordResetController;
use App\Http\Controllers\Staff\SessionController;
use App\Payments\Http\Controllers\PaymentInstallmentController;
use App\Payments\Http\Controllers\PaymentPlanController;
use App\Quotes\Http\Controllers\QuoteController;
use App\Settings\Http\Controllers\NumberRangeController;
use App\Settings\Http\Controllers\PaymentSettingsController;
use App\Settings\Http\Controllers\StudioClosureController;
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

Route::middleware('audience:staff')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);

    Route::get('/auth/mfa/enroll', [MfaEnrollmentController::class, 'create']);
    Route::post('/auth/mfa/confirm', [MfaEnrollmentController::class, 'confirm']);
    Route::get('/auth/mfa/recovery-codes', [MfaEnrollmentController::class, 'recoveryCodes']);

    Route::middleware('mfa')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        // German paths, English route names: the UI reads in one language and
        // the code reads in the other, and neither has to translate the other.
        //
        // /kontakte/suche stays above any /kontakte/{contact} route, or Laravel
        // binds "suche" as a contact public id and answers 404.
        Route::get('/kontakte/suche', ContactSearchController::class)->name('contacts.search');
        Route::get('/kontakte', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/kontakte/{contact}', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::patch('/kontakte/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::post('/kontakte/{contact}/portal-zugang/widerruf', [ContactController::class, 'revokePortalAccess'])
            ->name('contacts.portal-access.revoke');
        Route::get('/verfuegbarkeit', AvailabilityController::class)->name('availability.show');

        // /pakete/neu stays above /pakete/{package} for the same reason
        // /anfragen/neu stays above /anfragen/{lead}/stufe.
        Route::get('/pakete', [PackageController::class, 'index'])->name('packages.index');
        Route::get('/pakete/neu', [PackageController::class, 'create'])->name('packages.create');
        Route::post('/pakete', [PackageController::class, 'store'])->name('packages.store');
        Route::get('/pakete/{package}', [PackageController::class, 'edit'])->name('packages.edit');
        Route::patch('/pakete/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::post('/pakete/{package}/status', [PackageController::class, 'toggle'])->name('packages.toggle');

        // /vertragsvorlagen/neu stays above /vertragsvorlagen/{contractTemplate}
        // for the same reason /pakete/neu does.
        Route::get('/vertragsvorlagen', [ContractTemplateController::class, 'index'])->name('contract-templates.index');
        Route::get('/vertragsvorlagen/neu', [ContractTemplateController::class, 'create'])->name('contract-templates.create');
        Route::post('/vertragsvorlagen', [ContractTemplateController::class, 'store'])->name('contract-templates.store');
        Route::get('/vertragsvorlagen/{contractTemplate}', [ContractTemplateController::class, 'edit'])
            ->name('contract-templates.edit');
        Route::patch('/vertragsvorlagen/{contractTemplate}', [ContractTemplateController::class, 'update'])
            ->name('contract-templates.update');

        Route::get('/team', [InvitationController::class, 'index'])->name('staff.index');
        Route::post('/team/einladungen', [InvitationController::class, 'store'])->name('staff.invite');
        Route::delete('/team/einladungen/{invitation}', [InvitationController::class, 'destroy'])
            ->name('staff.invitation.destroy');

        Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar.index');
        Route::post('/kalender/abo', [CalendarController::class, 'regenerate'])
            ->name('calendar.regenerate');

        Route::get('/einstellungen', [StudioSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/einstellungen', [StudioSettingsController::class, 'update'])->name('settings.update');
        Route::get('/einstellungen/logo', [StudioSettingsController::class, 'logo'])->name('settings.logo');
        Route::patch('/einstellungen/ust', [StudioSettingsController::class, 'updateVat'])->name('settings.vat.update');
        Route::patch('/einstellungen/dokumente', [StudioSettingsController::class, 'updateDocuments'])->name('settings.documents.update');
        Route::patch('/einstellungen/allgemein', [StudioSettingsController::class, 'updateGeneral'])->name('settings.general.update');

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

        // /anfragen/neu stays above /anfragen/{lead}/stufe for the same reason
        // /kontakte/suche stays above /kontakte/{contact}.
        Route::get('/anfragen/neu', [InquiryCaptureController::class, 'create'])->name('leads.create');
        Route::get('/anfragen', LeadBoardController::class)->name('leads.index');
        Route::post('/anfragen', [InquiryCaptureController::class, 'store'])->name('leads.store');
        Route::post('/anfragen/{lead}/stufe', [LeadStageController::class, 'update'])
            ->name('leads.stage.update');

        // The project screen. Shoot and crew mutations hang off /shootings
        // rather than under the project, because the shoot's public id already
        // identifies it and nesting would let the two disagree.
        Route::get('/auftraege/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('/auftraege/{project}/shootings', [ProjectController::class, 'shoots'])
            ->name('projects.shoots');
        Route::post('/auftraege/{project}/buchung', [ProjectController::class, 'book'])
            ->name('projects.book');
        Route::post('/auftraege/{project}/portal-sichtbarkeit', [ProjectController::class, 'togglePortalVisibility'])
            ->name('projects.portal-visibility.toggle');

        Route::get('/auftraege/{project}/angebote', [ProjectController::class, 'quotes'])
            ->name('projects.quotes');

        Route::post('/auftraege/{project}/angebote', [QuoteController::class, 'store'])
            ->name('quotes.store');
        Route::patch('/angebote/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
        Route::post('/angebote/{quote}/versenden', [QuoteController::class, 'send'])->name('quotes.send');

        Route::get('/auftraege/{project}/vertrag', [ContractController::class, 'show'])
            ->name('projects.contract');
        Route::post('/auftraege/{project}/vertraege', [ContractController::class, 'store'])
            ->name('contracts.store');
        Route::get('/vertraege/{contract}/pdf', [ContractController::class, 'download'])
            ->name('contracts.download');

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

        Route::post('/auftraege/{project}/aufgaben', [TaskController::class, 'store'])
            ->name('tasks.store');
        Route::post('/aufgaben/{task}/erledigt', [TaskController::class, 'complete'])
            ->name('tasks.complete');
        Route::delete('/aufgaben/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        Route::post('/auftraege/{project}/notizen', [InternalNoteController::class, 'store'])
            ->name('notes.store');
        Route::patch('/notizen/{note}', [InternalNoteController::class, 'update'])->name('notes.update');
        Route::delete('/notizen/{note}', [InternalNoteController::class, 'destroy'])
            ->name('notes.destroy');
    });
});
