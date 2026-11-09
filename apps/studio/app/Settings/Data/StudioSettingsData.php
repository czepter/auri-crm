<?php

namespace App\Settings\Data;

use App\Models\StudioSettings;
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
 * StudioSettings' logo_path for the stored-once file this is
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
        /** @var array<string, string|null> occasion => template family */
        public array $mailDefaults,
        public bool $mailHeaderName,
        public bool $mailFooterLegal,
        public ?string $mailFooterExtra,
    ) {}

    public static function current(): self
    {
        $attrs = StudioSettings::current()->profile();

        return new self(
            id: StudioSettings::current()->public_id,
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
            logoUrl: StudioSettings::current()->logo_path !== null ? route('settings.logo') : null,
            bankAccountHolder: $attrs['bank_account_holder'],
            bankIban: $attrs['bank_iban'],
            bankBic: $attrs['bank_bic'],
            vatMode: StudioSettings::current()->vat_mode,
            vatRate: StudioSettings::current()->vat_rate,
            vatNote: StudioSettings::current()->vat_note,
            quoteValidityDays: StudioSettings::current()->quote_validity_days,
            paymentTermDays: StudioSettings::current()->payment_term_days,
            depositPercentage: StudioSettings::current()->deposit_percentage,
            documentLayout: StudioSettings::current()->document_layout,
            remindersEnabled: (bool) StudioSettings::current()->payments_reminders_enabled,
            clientRegister: StudioSettings::current()->client_register,
            documentLetterheadHtml: StudioSettings::current()->document_letterhead_html,
            documentFooterHtml: StudioSettings::current()->document_footer_html,
            timezone: StudioSettings::current()->localTimezone(),
            workingDays: StudioSettings::current()->workingDays(),
            mailFromName: StudioSettings::current()->mailFromName(),
            mailFromAddress: StudioSettings::current()->mailFromAddress(),
            mailReplyTo: StudioSettings::current()->mail_reply_to,
            mailSignature: StudioSettings::current()->mail_signature,
            mailBcc: StudioSettings::current()->mail_bcc,
            mailClientsEnabled: (bool) StudioSettings::current()->mail_clients_enabled,
            mailDefaults: StudioSettings::current()->mail_defaults ?? [],
            mailHeaderName: (bool) StudioSettings::current()->mail_header_name,
            mailFooterLegal: (bool) StudioSettings::current()->mail_footer_legal,
            mailFooterExtra: StudioSettings::current()->mail_footer_extra,
        );
    }
}
