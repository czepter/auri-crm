<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-05: the idempotency ledger a redelivered Stripe webhook is checked
 * against. `stripe_event_id` is unique — Stripe's own event ID is the key,
 * not anything this app generates — so a duplicate delivery's INSERT fails
 * the same way a duplicate INSERT into any other unique column would, and
 * App\Payments\Http\Controllers\StripeWebhookController treats exactly that
 * failure as "already seen", not an error.
 *
 * Carries tenant_id via the same tenantId() macro every other table uses,
 * defaulting to config('tenancy.tenant_id') — this deployment is still the
 * single-tenant one config/tenancy.php describes ("one studio today"), so
 * there is no per-request tenant to resolve at receipt time, here or
 * anywhere else in this codebase yet. The signing secret this event is
 * verified against is a single deployment-wide value (config('services.
 * stripe.webhook_secret'), the same config/services.php shape as every other
 * single third-party credential this app holds), not a per-tenant one, which
 * is what makes the row genuinely tenant-agnostic at the moment it is
 * written. It only becomes tenant-scoped in the ordinary sense once
 * ProcessStripeWebhookEvent resolves the event to a PaymentInstallment,
 * whose own BelongsToTenant scope is what actually enforces the boundary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('stripe_event_id')->unique();
            $table->string('type');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
    }
};
