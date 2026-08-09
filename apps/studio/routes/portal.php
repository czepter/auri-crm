<?php

use App\Portal\Http\Controllers\HomeController;
use App\Portal\Http\Controllers\MagicLinkController;
use Illuminate\Support\Facades\Route;

// Client surface. A different audience from the studio's, on its own session
// cookie and its own guard — a Contact is not a User, and no session
// established here or there is worth anything on the other.

// The way in. Public by necessity: someone who cannot sign in is the only
// person who ever needs it. Listed in the audience matrix's PUBLIC_ROUTES, so
// it stays a decision rather than an omission.
Route::middleware('guest:portal')->group(function () {
    Route::get('/anmelden', [MagicLinkController::class, 'create'])->name('portal.login');
    Route::post('/anmelden', [MagicLinkController::class, 'store'])->name('portal.login.request');

    // The link itself. Single use, fifteen minutes, and the token in the path
    // is the whole of the credential — so it is spent the moment it works.
    Route::get('/zugang/{token}', [MagicLinkController::class, 'consume'])->name('portal.access');
});

Route::middleware('audience:portal')->group(function () {
    Route::post('/abmelden', [MagicLinkController::class, 'destroy'])->name('portal.logout');

    Route::get('/', HomeController::class)->name('portal.home');
});
