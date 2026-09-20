<?php

namespace App\Settings\Data;

use App\Settings\Support\StudioSettingsResolver;
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
        /** #234: which of the four licensed pairs this studio writes in. */
        public ?string $fontPair,
        public ?string $logoUrl,
        public ?string $bankAccountHolder,
        public ?string $bankIban,
        public ?string $bankBic,
        public string $vatMode,
        public int $vatRate,
        public ?string $vatNote,
        public int $quoteValidityDays,
        public int $paymentTermDays,
        public int $depositPercentage,
        public string $documentLayout,
        public bool $remindersEnabled,
        public string $clientRegister,
        public ?string $documentLetterheadHtml,
        public ?string $documentFooterHtml,
        public string $timezone,
        /** @var list<int> */
        public array $workingDays,
        public string $mailFromName,
        public string $mailFromAddress,
        public ?string $mailReplyTo,
        public ?string $mailSignature,
        public ?string $mailBcc,
        public bool $mailClientsEnabled,
    ) {}

    public static function fromResolver(StudioSettingsResolver $settings): self
    {
        $attrs = $settings->attributes();

        return new self(
            id: $settings->publicId(),
            displayName: $attrs['display_name'],
            legalName: $attrs['legal_name'],
            legalForm: $attrs['legal_form'],
            street: $attrs['street'],
            postcode: $attrs['postcode'],
            city: $attrs['city'],
            country: $attrs['country'],
            taxNumber: $attrs['tax_number'],
            vatId: $attrs['vat_id'],
            registerNumber: $attrs['register_number'],
            registerCourt: $attrs['register_court'],
            representative: $attrs['representative'],
            website: $attrs['website'],
            accentColor: $attrs['accent_color'],
            fontPair: $attrs['font_pair'] ?? null,
            logoUrl: $settings->logoPath() !== null ? route('settings.logo') : null,
            bankAccountHolder: $attrs['bank_account_holder'],
            bankIban: $attrs['bank_iban'],
            bankBic: $attrs['bank_bic'],
            vatMode: $settings->vatMode(),
            vatRate: $settings->vatRate(),
            vatNote: $settings->vatNote(),
            quoteValidityDays: $settings->quoteValidityDays(),
            paymentTermDays: $settings->paymentTermDays(),
            depositPercentage: $settings->depositPercentage(),
            documentLayout: $settings->documentLayout(),
            remindersEnabled: $settings->remindersEnabled(),
            clientRegister: $settings->clientRegister(),
            documentLetterheadHtml: $settings->documentLetterheadHtml(),
            documentFooterHtml: $settings->documentFooterHtml(),
            timezone: $settings->timezone(),
            workingDays: $settings->workingDays(),
            mailFromName: $settings->mailFromName(),
            mailFromAddress: $settings->mailFromAddress(),
            mailReplyTo: $settings->mailReplyTo(),
            mailSignature: $settings->mailSignature(),
            mailBcc: $settings->mailBcc(),
            mailClientsEnabled: $settings->clientMailEnabled(),
        );
    }
}
