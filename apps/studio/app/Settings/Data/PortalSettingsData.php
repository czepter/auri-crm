<?php

namespace App\Settings\Data;

use App\Models\StudioSettings;
use App\Portal\Support\LegalTexts;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-08's own pane, extended by P-09 (#228): the maintenance closure, one
 * welcome text per opening state (nothing booked, a booking under way,
 * everything delivered), the two external legal
 * links, the client capability switches (messaging among them, as three
 * states rather than a boolean — see P-04), and the contact line the portal footer had no source for.
 * Deliberately carries none of T-02's branding fields (logo, accent colour,
 * display name) a second time — those are edited on Studio-Profil and read
 * portal-wide through HandleInertiaRequests's `portalBranding` prop, never
 * duplicated here as a second writable copy.
 */
#[TypeScript]
class PortalSettingsData extends Data
{
    public function __construct(
        public string $id,
        public bool $maintenanceEnabled,
        public ?string $maintenanceMessage,
        public ?string $datenschutzUrl,
        public ?string $agbUrl,
        public ?string $impressumUrl,
        public ?string $widerrufUrl,
        public bool $agbAttach,
        public bool $widerrufAttach,
        /** @var array<string, ?string> Vorlagen-Familie je Rechtstext */
        public array $legalFamilies,
        public bool $acceptQuotesEnabled,
        public bool $paymentStatusEnabled,
        public bool $offersEnabled,
        public string $messagingMode,
        public string $clientRegister,
        public ?string $welcomeInquiry,
        public ?string $welcomeActive,
        public ?string $welcomeDone,
        public ?string $contactName,
        public ?string $contactAvatarUrl,
        public ?string $contactPhone,
        public ?string $contactEmail,
        public ?string $contactResponseTime,
    ) {}

    public static function current(): self
    {
        return new self(
            id: StudioSettings::current()->public_id,
            maintenanceEnabled: (bool) StudioSettings::current()->portal_maintenance_enabled,
            maintenanceMessage: StudioSettings::current()->portal_maintenance_message,
            datenschutzUrl: LegalTexts::externalUrl('datenschutz'),
            agbUrl: LegalTexts::externalUrl('agb'),
            impressumUrl: LegalTexts::externalUrl('impressum'),
            widerrufUrl: LegalTexts::externalUrl('widerruf'),
            agbAttach: LegalTexts::attach('agb'),
            widerrufAttach: LegalTexts::attach('widerruf'),
            legalFamilies: collect(LegalTexts::ALL)
                ->mapWithKeys(fn (string $text) => [$text => LegalTexts::family($text)])
                ->all(),
            acceptQuotesEnabled: (bool) StudioSettings::current()->portal_accept_quotes_enabled,
            paymentStatusEnabled: (bool) StudioSettings::current()->portal_payment_status_enabled,
            offersEnabled: (bool) StudioSettings::current()->portal_offers_enabled,
            messagingMode: (string) StudioSettings::current()->portal_messaging_mode,
            clientRegister: StudioSettings::current()->client_register,
            welcomeInquiry: StudioSettings::current()->portal_welcome_inquiry,
            welcomeActive: StudioSettings::current()->portal_welcome_active,
            welcomeDone: StudioSettings::current()->portal_welcome_done,
            contactName: StudioSettings::current()->portal_contact_name,
            contactAvatarUrl: StudioSettings::current()->portal_contact_avatar_path !== null ? route('settings.portal.contact.avatar') : null,
            contactPhone: StudioSettings::current()->portal_contact_phone,
            contactEmail: StudioSettings::current()->portal_contact_email,
            contactResponseTime: StudioSettings::current()->portal_contact_response_time,
        );
    }
}
