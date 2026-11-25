<?php

namespace App\Settings\Data;

use App\Enums\ContractMode;
use App\Models\StudioSettings;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContractSettingsData extends Data
{
    public function __construct(
        public ContractMode $mode,
        public bool $signOnline,
    ) {}

    public static function current(): self
    {
        $settings = StudioSettings::current();

        return new self(
            mode: $settings->contractMode(),
            signOnline: (bool) $settings->portal_sign_contracts_enabled,
        );
    }
}
