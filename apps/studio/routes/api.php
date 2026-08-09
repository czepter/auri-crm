<?php

use App\Http\Controllers\HealthController;
use App\Payments\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Public JSON surface. In P1a this carries the health probe only; P1b adds
// the public inquiry endpoint and P3 adds the gallery read API.
Route::get('/health', HealthController::class);

// M-05: the first api-surface consumer of user-facing business logic
// (everything above is infrastructure). No `auth` middleware — Stripe is the
// only caller, and it authenticates itself, at the controller, by the
// request signature — see StripeWebhookController's own doc comment.
Route::post('/stripe/webhook', StripeWebhookController::class)->name('api.stripe.webhook');
