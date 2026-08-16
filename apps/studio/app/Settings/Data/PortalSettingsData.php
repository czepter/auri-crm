<?php

namespace App\Settings\Data;

use App\Settings\Support\StudioSettingsResolver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-08's own pane, extended by P-09 (#228): the maintenance closure, three
 * welcome texts for the portal's three opening states, the two external legal
 * links, the client capability switches (messaging among them, as three
 * states rather than a boolean — see P-04), the gallery defaults a studio
 * would otherwise re-enter on every delivery, where a DSGVO request goes and
 * by when, and the contact line the portal footer had no source for.
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
        public ?string $welcomeText,
        public ?string $datenschutzUrl,
        public ?string $agbUrl,
        public bool $acceptQuotesEnabled,
        public bool $signContractsEnabled,
        public bool $paymentStatusEnabled,
        public string $messagingMode,
        public ?string $welcomeInquiry,
        public ?string $welcomeMany,
        public ?int $galleryExpiryDays,
        public bool $galleryRequiresSettledBalance,
        public bool $galleryDownloadsEnabled,
        public bool $galleryListsEnabled,
        public bool $galleryCommentsEnabled,
        public ?string $dsgvoRecipient,
        public int $dsgvoDeadlineDays,
        public ?string $contactPhone,
        public ?string $contactEmail,
        public ?string $contactResponseTime,
    ) {}

    public static function fromResolver(StudioSettingsResolver $settings): self
    {
        return new self(
            id: $settings->publicId(),
            maintenanceEnabled: $settings->maintenanceEnabled(),
            maintenanceMessage: $settings->maintenanceMessage(),
            welcomeText: $settings->portalWelcomeText(),
            datenschutzUrl: $settings->legalDatenschutzUrl(),
            agbUrl: $settings->legalAgbUrl(),
            acceptQuotesEnabled: $settings->acceptQuotesEnabled(),
            signContractsEnabled: $settings->signContractsEnabled(),
            paymentStatusEnabled: $settings->paymentStatusEnabled(),
            messagingMode: $settings->messagingMode(),
            welcomeInquiry: $settings->portalWelcomeInquiry(),
            welcomeMany: $settings->portalWelcomeMany(),
            galleryExpiryDays: $settings->galleryDefaults()['expiryDays'],
            galleryRequiresSettledBalance: $settings->galleryDefaults()['requiresSettledBalance'],
            galleryDownloadsEnabled: $settings->galleryDownloadsEnabled(),
            galleryListsEnabled: $settings->galleryListsEnabled(),
            galleryCommentsEnabled: $settings->galleryCommentsEnabled(),
            dsgvoRecipient: $settings->dsgvoRequestRecipient(),
            dsgvoDeadlineDays: $settings->dsgvoRequestDeadlineDays(),
            contactPhone: $settings->portalContactPhone(),
            contactEmail: $settings->portalContactEmail(),
            contactResponseTime: $settings->portalContactResponseTime(),
        );
    }
}
