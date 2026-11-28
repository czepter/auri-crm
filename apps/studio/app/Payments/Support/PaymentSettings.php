<?php

namespace App\Payments\Support;

use App\Models\StudioSettings;
use App\Payments\Data\PaymentInstallmentData;
use App\Payments\Enums\PaymentInstallmentStatus;
use Carbon\Carbon;

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

    /**
     * What the portal offers "Bezahlen" for: a payment method exists and the
     * rate is late, due within 14 days, or can only be transferred anyway.
     */
    public static function payableNow(PaymentInstallmentData $row): bool
    {
        if (! in_array($row->status, array_column(PaymentInstallmentStatus::open(), 'value'), true)) {
            return false;
        }

        $settings = StudioSettings::current();
        $stripe = (bool) $settings->payments_stripe_enabled;
        $iban = filled($settings->bank_iban);

        return ($stripe || $iban)
            && ($row->isLate || Carbon::parse($row->dueOn)->lte(today()->addDays(14)) || ! $stripe);
    }

    /** Anything but 'live' reads the test pair. */
    private static function key(string $mode, string $kind): ?string
    {
        return StudioSettings::current()->{'payments_stripe_'.($mode === 'live' ? 'live' : 'test')."_{$kind}_key"};
    }
}
