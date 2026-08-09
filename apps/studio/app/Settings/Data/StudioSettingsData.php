<?php

namespace App\Settings\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The settings row as the admin surface sees it. `id` is the public_id —
 * never listed or linked to, but present anyway per the Definition of Done:
 * no integer identifier in any Inertia prop, this row included.
 *
 * `logoUrl` is the one route the logo is ever served from
 * (StudioSettingsController::logo) — never the raw storage path, which
 * would leak a filesystem detail into the client. See
 * StudioSettingsResolver::logoPath() for the stored-once file this is
 * built from.
 */
#[TypeScript]
class StudioSettingsData extends Data
{
    public function __construct(
        public string $id,
        public ?string $displayName,
        public ?string $legalName,
        public ?string $legalForm,
        public ?string $street,
        public ?string $postcode,
        public ?string $city,
        public ?string $country,
        public ?string $taxNumber,
        public ?string $vatId,
        public ?string $registerNumber,
        public ?string $registerCourt,
        public ?string $representative,
        public ?string $website,
        public ?string $accentColor,
        public ?string $logoUrl,
        public ?string $bankAccountHolder,
        public ?string $bankIban,
        public ?string $bankBic,
        public string $vatMode,
        public int $vatRate,
        public ?string $vatNote,
    ) {}
}
