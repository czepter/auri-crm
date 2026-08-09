<?php

use App\Portal\Http\Controllers\ContractController;
use App\Portal\Http\Controllers\HomeController;
use App\Portal\Http\Controllers\MagicLinkController;
use App\Portal\Http\Controllers\PaymentCheckoutController;
use App\Portal\Http\Controllers\PaymentController;
use App\Portal\Http\Controllers\ProjectController;
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

    // The switcher. Zero visible projects is an empty state here rather than
    // an error, and exactly one skips the picker by redirecting straight to
    // it — see HomeController.
    Route::get('/', HomeController::class)->name('portal.home');

    // Direct entry. Bound on the public id and re-asking the same visibility
    // scope HomeController asks — see ProjectController — so a project the
    // contact is not on, or one the studio has not made portal_visible, is a
    // 404 here exactly as it is absent from the switcher. Never a 403: the
    // difference between "not yours" and "does not exist" is not this
    // surface's to give away.
    Route::get('/projekte/{project}', ProjectController::class)->name('portal.projects.show');

    // Mockup 07. Reads only the artifact M-01's queued job already stored —
    // see App\Portal\Http\Controllers\ContractController.
    Route::get('/projekte/{project}/vertrag', [ContractController::class, 'show'])
        ->name('portal.contracts.show');
    Route::get('/projekte/{project}/vertrag/pdf', [ContractController::class, 'download'])
        ->name('portal.contracts.download');

    // M-02's signing rail. Writes at most one contract_signatures row per
    // contract — see App\Contracts\Actions\SignContract.
    Route::post('/projekte/{project}/vertrag/unterschreiben', [ContractController::class, 'sign'])
        ->name('portal.contracts.sign');

    // T-07. Read-only apart from M-04's own confirm step on return from
    // Stripe — nothing here ever CREATES a Stripe object, see App\Portal\
    // Http\Controllers\PaymentController.
    Route::get('/projekte/{project}/zahlungen', PaymentController::class)
        ->name('portal.payments.show');

    // M-04. The client's own click, and the only route that ever creates a
    // Stripe object — see App\Payments\Actions\CreateInstallmentCheckoutSession.
    Route::post('/projekte/{project}/zahlungen/{installment}/jetzt-zahlen', PaymentCheckoutController::class)
        ->name('portal.payments.checkout');
});
