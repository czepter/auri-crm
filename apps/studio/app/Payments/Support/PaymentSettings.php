<?php

namespace App\Payments\Support;

use App\Models\StudioSettings;

/** T-07: Stripe as an optional capability, with a key pair per mode. */
final class PaymentSettings
{
    /**
     * Whether a key pair exists for the given mode — the settings screen's
     * own "already configured" indicator, so the write-only key fields can
     * say that much without ever echoing a value back. Never used to decide
     * whether Stripe may actually be called; that is activeStripeSecretKey()'s
     * job.
     */
    public static function hasStripeKeys(string $mode): bool
    {
        $row = StudioSettings::current();

        return $mode === 'live'
            ? $row->payments_stripe_live_publishable_key !== null && $row->payments_stripe_live_secret_key !== null
            : $row->payments_stripe_test_publishable_key !== null && $row->payments_stripe_test_secret_key !== null;
    }

    /**
     * The secret key for whichever mode is active, decrypted by the model's
     * own `encrypted` cast. Nothing ever prints this value; see
     * StudioSettingsAdminTest's "the Stripe secret key never appears in any
     * response body" coverage.
     */
    public static function activeStripeSecretKey(): ?string
    {
        $row = StudioSettings::current();

        return $row->payments_stripe_mode === 'live'
            ? $row->payments_stripe_live_secret_key
            : $row->payments_stripe_test_secret_key;
    }

    public static function activeStripePublishableKey(): ?string
    {
        $row = StudioSettings::current();

        return $row->payments_stripe_mode === 'live'
            ? $row->payments_stripe_live_publishable_key
            : $row->payments_stripe_test_publishable_key;
    }
}
