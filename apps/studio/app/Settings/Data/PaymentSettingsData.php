<?php

namespace App\Settings\Data;

use App\Settings\Support\StudioSettingsResolver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-07's own pane — Stripe as a capability the studio may switch on, plus
 * the reminders default. Deliberately carries no Stripe key of either mode,
 * not even the publishable one, and not even masked: nothing on the
 * settings screen this story ships consumes a key value client-side, so the
 * simplest and safest thing is to never put one in a response body at all.
 * `hasStripeTestKeys` / `hasStripeLiveKeys` are the only signal the form
 * gets about whether a pair already exists, the same "already configured"
 * indicator a changed-password form gives without echoing the old one.
 *
 * `depositPercent` and `zahlungszielDays` are read-only here, sourced from
 * T-05's own columns (StudioSettingsResolver::depositPercentage() and
 * ::paymentTermDays()) — shown for context on this pane, edited on T-05's
 * Dokumente pane, never duplicated as a second writable copy.
 */
#[TypeScript]
class PaymentSettingsData extends Data
{
    public function __construct(
        public string $id,
        public bool $stripeEnabled,
        public string $stripeMode,
        public bool $hasStripeTestKeys,
        public bool $hasStripeLiveKeys,
        public bool $remindersEnabled,
        public int $depositPercent,
        public int $zahlungszielDays,
    ) {}

    public static function fromResolver(StudioSettingsResolver $settings): self
    {
        return new self(
            id: $settings->publicId(),
            stripeEnabled: $settings->stripeEnabled(),
            stripeMode: $settings->stripeMode(),
            hasStripeTestKeys: $settings->hasStripeKeys('test'),
            hasStripeLiveKeys: $settings->hasStripeKeys('live'),
            remindersEnabled: $settings->remindersEnabled(),
            depositPercent: $settings->depositPercentage(),
            zahlungszielDays: $settings->paymentTermDays(),
        );
    }
}
