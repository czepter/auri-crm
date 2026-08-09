<?php

use App\Crm\Http\Controllers\AvailabilityController;
use App\Crm\Http\Controllers\ContactSearchController;
use App\Crm\Http\Controllers\InquiryCaptureController;
use App\Crm\Http\Controllers\LeadBoardController;
use App\Crm\Http\Controllers\LeadStageController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\MfaChallengeController;
use App\Http\Controllers\Staff\MfaEnrollmentController;
use App\Http\Controllers\Staff\SessionController;
use Illuminate\Support\Facades\Route;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
Route::middleware('guest:staff')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);

    // The challenge runs between password and session: authenticated by the
    // pending-user session key, not by the staff guard.
    Route::get('/auth/mfa', [MfaChallengeController::class, 'create']);
    Route::post('/auth/mfa', [MfaChallengeController::class, 'store']);
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
        Route::get('/verfuegbarkeit', AvailabilityController::class)->name('availability.show');

        // /anfragen/neu stays above /anfragen/{lead}/stufe for the same reason
        // /kontakte/suche stays above /kontakte/{contact}.
        Route::get('/anfragen/neu', [InquiryCaptureController::class, 'create'])->name('leads.create');
        Route::get('/anfragen', LeadBoardController::class)->name('leads.index');
        Route::post('/anfragen', [InquiryCaptureController::class, 'store'])->name('leads.store');
        Route::post('/anfragen/{lead}/stufe', [LeadStageController::class, 'update'])
            ->name('leads.stage.update');
    });
});
