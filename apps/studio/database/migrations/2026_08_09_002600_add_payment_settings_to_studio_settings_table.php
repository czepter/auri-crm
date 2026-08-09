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
 * `payments_reminders_enabled` is the one provider-independent default
 * T-07's own scope named that T-05 did not already cover — deposit
 * percentage and Zahlungsziel days landed first as T-05's
 * deposit_percentage and payment_term_days (the Dokumente pane,
 * 2026_08_09_002600_add_document_defaults_to_studio_settings_table), so this
 * migration does not repeat either.
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

            $table->boolean('payments_reminders_enabled')->default(true);
        });

        // Postgres has no native enum-by-column-type here, matching every
        // other mode column this table already carries (vat_mode) — the
        // same belt-and-suspenders reasoning as studio_settings_vat_mode_check.
        DB::statement("alter table studio_settings add constraint studio_settings_stripe_mode_check check (payments_stripe_mode in ('test', 'live'))");
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payments_stripe_enabled', 'payments_stripe_mode',
                'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
                'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
                'payments_reminders_enabled',
            ]);
        });
    }
};
