<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-07: Stripe becomes a capability the studio may switch on — defaulting
 * to OFF — rather than an assumption M-04/M-05 get to bake into their own
 * schema. Explicit test/live mode, each with its own key pair, so flipping
 * `payments_stripe_mode` never requires re-entering keys: both pairs always
 * live on this row, and only the active mode's pair is ever read (see
 * StudioSettingsResolver::activeStripeSecretKey()).
 *
 * The four key columns are `text`, not `string` — the model's `encrypted`
 * cast (App\Settings\Models\StudioSettings) stores ciphertext plus an IV,
 * which is longer than the plaintext key it replaces and would silently
 * truncate under a 255-char varchar. Nothing here, and nothing this
 * migration seeds, ever writes one in plaintext.
 *
 * deposit_percent, zahlungsziel_days and reminders_enabled are the
 * provider-independent defaults T-07's own scope asks for — read by every
 * future PaymentPlan regardless of whether Stripe is ever switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->boolean('payments_stripe_enabled')->default(false);

            // 'test' or 'live' — see the check constraint below.
            $table->string('payments_stripe_mode')->default('test');

            $table->text('payments_stripe_test_publishable_key')->nullable();
            $table->text('payments_stripe_test_secret_key')->nullable();
            $table->text('payments_stripe_live_publishable_key')->nullable();
            $table->text('payments_stripe_live_secret_key')->nullable();

            // Basis points (0-10000), never a float — the same rule
            // studio_settings.vat_rate (T-03) already follows.
            $table->unsignedInteger('payments_deposit_percent')->default(3000);
            $table->unsignedInteger('payments_zahlungsziel_days')->default(14);
            $table->boolean('payments_reminders_enabled')->default(true);
        });

        // Postgres has no native enum-by-column-type here, matching every
        // other mode column this table already carries (vat_mode) — the
        // same belt-and-suspenders reasoning as studio_settings_vat_mode_check.
        DB::statement("alter table studio_settings add constraint studio_settings_stripe_mode_check check (payments_stripe_mode in ('test', 'live'))");
        DB::statement('alter table studio_settings add constraint studio_settings_deposit_percent_check check (payments_deposit_percent >= 0 and payments_deposit_percent <= 10000)');
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payments_stripe_enabled', 'payments_stripe_mode',
                'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
                'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
                'payments_deposit_percent', 'payments_zahlungsziel_days', 'payments_reminders_enabled',
            ]);
        });
    }
};
