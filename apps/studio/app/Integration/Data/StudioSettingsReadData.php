<?php

namespace App\Integration\Data;

use App\Settings\Support\StudioSettingsResolver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What create_quote needs to reason about a studio's own defaults, and
 * nothing more — no mail credentials, no bank details, no Stripe keys. See
 * App\Settings\Data\StudioSettingsData for the admin surface's full row.
 */
#[TypeScript]
class StudioSettingsReadData extends Data
{
    public function __construct(
        public string $vatMode,
        public int $vatRate,
        public int $quoteValidityDays,
        public int $paymentTermDays,
        public int $depositPercentage,
    ) {}

    public static function fromResolver(StudioSettingsResolver $settings): self
    {
        return new self(
            vatMode: $settings->vatMode(),
            vatRate: $settings->vatRate(),
            quoteValidityDays: $settings->quoteValidityDays(),
            paymentTermDays: $settings->paymentTermDays(),
            depositPercentage: $settings->depositPercentage(),
        );
    }
}
