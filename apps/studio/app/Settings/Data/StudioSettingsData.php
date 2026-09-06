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
        // Q-10: die Schaufensterbilder als Routen, nie als Pfade — dieselbe
        // Regel, die logoUrl daneben schon traegt.
        /** @var list<string> */
        public array $showcaseUrls,
        public ?string $bankAccountHolder,
        public ?string $bankIban,
        public ?string $bankBic,
        public string $vatMode,
        public int $vatRate,
        public ?string $vatNote,
        public int $quoteValidityDays,
        public int $paymentTermDays,
        public int $depositPercentage,
        public bool $widerrufIncluded,
        public ?string $widerrufText,
        public ?string $documentLetterheadHtml,
        public ?string $documentFooterHtml,
        public string $timezone,
        public string $locale,
        /** @var list<int> */
        public array $workingDays,
        public string $mailFromName,
        public string $mailFromAddress,
        public ?string $mailReplyTo,
        public ?string $mailSignature,
        public ?string $mailBcc,
        /** @var list<string> */
        public array $leadTypePresets,
        /** @var list<string> */
        public array $leadSourcePresets,
        /** @var list<string> */
        public array $leadChannelPresets,
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
            showcaseUrls: collect($settings->showcasePaths())
                ->keys()
                ->map(fn (int $index) => route('settings.showcase', $index))
                ->all(),
            bankAccountHolder: $attrs['bank_account_holder'],
            bankIban: $attrs['bank_iban'],
            bankBic: $attrs['bank_bic'],
            vatMode: $settings->vatMode(),
            vatRate: $settings->vatRate(),
            vatNote: $settings->vatNote(),
            quoteValidityDays: $settings->quoteValidityDays(),
            paymentTermDays: $settings->paymentTermDays(),
            depositPercentage: $settings->depositPercentage(),
            widerrufIncluded: $settings->widerrufIncluded(),
            widerrufText: $settings->widerrufText(),
            documentLetterheadHtml: $settings->documentLetterheadHtml(),
            documentFooterHtml: $settings->documentFooterHtml(),
            timezone: $settings->timezone(),
            locale: $settings->locale(),
            workingDays: $settings->workingDays(),
            mailFromName: $settings->mailFromName(),
            mailFromAddress: $settings->mailFromAddress(),
            mailReplyTo: $settings->mailReplyTo(),
            mailSignature: $settings->mailSignature(),
            mailBcc: $settings->mailBcc(),
            leadTypePresets: $settings->leadPresets('type'),
            leadSourcePresets: $settings->leadPresets('source'),
            leadChannelPresets: $settings->leadPresets('channel'),
        );
    }
}
