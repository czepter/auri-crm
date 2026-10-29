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
        return self::key($mode, 'publishable') !== null && self::key($mode, 'secret') !== null;
    }

    /**
     * The secret key for whichever mode is active, decrypted by the model's
     * own `encrypted` cast. Nothing ever prints this value; see
     * StudioSettingsAdminTest's "the Stripe secret key never appears in any
     * response body" coverage.
     */
    public static function activeStripeSecretKey(): ?string
    {
        return self::key(StudioSettings::current()->payments_stripe_mode, 'secret');
    }

    public static function activeStripePublishableKey(): ?string
    {
        return self::key(StudioSettings::current()->payments_stripe_mode, 'publishable');
    }

    /** Anything but 'live' reads the test pair. */
    private static function key(string $mode, string $kind): ?string
    {
        return StudioSettings::current()->{'payments_stripe_'.($mode === 'live' ? 'live' : 'test')."_{$kind}_key"};
    }
}
