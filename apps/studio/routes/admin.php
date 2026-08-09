<?php

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
    });
});
