<?php

namespace App\Help\Support;

use App\Engagement\Support\InboundAddress;
use App\Settings\Support\StudioSettingsResolver;

final class HelpPlaceholders
{
    public function __construct(private readonly StudioSettingsResolver $settings) {}

    /** @return array<string, string> */
    public function werte(): array
    {
        return [
            'api_url' => (string) config('services.api.url'),
            'mcp_url' => (string) config('services.mcp.url'),
            'studio_name' => (string) ($this->settings->displayName() ?? ''),
            // Derselbe Weg wie StudioOnboardingController:56 — die Adresse
            // wird gemintet, nicht gespeichert, und InboundAddress ist die
            // einzige Stelle, die ihre Form kennt.
            'inbound_address' => (string) (
                $this->settings->inboundSecret() === null
                    ? ''
                    : InboundAddress::catchAll(
                        $this->settings->displayName() ?? '',
                        $this->settings->inboundSecret(),
                    )
            ),
        ];
    }
}
