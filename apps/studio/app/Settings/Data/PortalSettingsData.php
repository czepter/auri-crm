<?php

namespace App\Settings\Data;

use App\Settings\Support\StudioSettingsResolver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-08's own pane: the maintenance closure, the portal home's welcome text,
 * the two external legal links, and the four client capability switches.
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
        public bool $messagingEnabled,
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
            messagingEnabled: $settings->messagingEnabled(),
        );
    }
}
