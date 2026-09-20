<?php

namespace App\Settings\Data;

use App\Portal\Http\Controllers\LegalTextController;
use App\Settings\Support\StudioSettingsResolver;
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
        public bool $signContractsEnabled,
        public bool $paymentStatusEnabled,
        public bool $offersEnabled,
        public bool $galleryDownloadsEnabled,
        public bool $galleryListsEnabled,
        public bool $galleryCommentsEnabled,
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

    public static function fromResolver(StudioSettingsResolver $settings): self
    {
        return new self(
            id: $settings->publicId(),
            maintenanceEnabled: $settings->maintenanceEnabled(),
            maintenanceMessage: $settings->maintenanceMessage(),
            datenschutzUrl: $settings->legalDatenschutzUrl(),
            agbUrl: $settings->legalAgbUrl(),
            impressumUrl: $settings->legalTextUrl('impressum'),
            widerrufUrl: $settings->legalTextUrl('widerruf'),
            agbAttach: $settings->legalAttach('agb'),
            widerrufAttach: $settings->legalAttach('widerruf'),
            legalFamilies: collect(LegalTextController::TEXTS)
                ->mapWithKeys(fn (string $text) => [$text => $settings->legalFamily($text)])
                ->all(),
            acceptQuotesEnabled: $settings->acceptQuotesEnabled(),
            signContractsEnabled: $settings->signContractsEnabled(),
            paymentStatusEnabled: $settings->paymentStatusEnabled(),
            offersEnabled: $settings->offersEnabled(),
            galleryDownloadsEnabled: $settings->galleryDownloadsEnabled(),
            galleryListsEnabled: $settings->galleryListsEnabled(),
            galleryCommentsEnabled: $settings->galleryCommentsEnabled(),
            messagingMode: $settings->messagingMode(),
            clientRegister: $settings->clientRegister(),
            welcomeInquiry: $settings->portalWelcomeInquiry(),
            welcomeActive: $settings->portalWelcomeActive(),
            welcomeDone: $settings->portalWelcomeDone(),
            contactName: $settings->portalContactName(),
            contactAvatarUrl: $settings->portalContactAvatarPath() !== null ? route('settings.portal.contact.avatar') : null,
            contactPhone: $settings->portalContactPhone(),
            contactEmail: $settings->portalContactEmail(),
            contactResponseTime: $settings->portalContactResponseTime(),
        );
    }
}
