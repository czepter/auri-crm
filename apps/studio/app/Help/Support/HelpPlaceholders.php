<?php

namespace App\Help\Support;

use App\Engagement\Support\InboundAddress;
use App\Models\StudioSettings;

final class HelpPlaceholders
{
    /** @return array<string, string> */
    public function werte(): array
    {
        return [
            'api_url' => (string) config('services.api.url'),
            'mcp_url' => (string) config('services.mcp.url'),
            'studio_name' => (string) (StudioSettings::current()->display_name ?? ''),
            // Derselbe Weg wie StudioOnboardingController:56 — die Adresse
            // wird gemintet, nicht gespeichert, und InboundAddress ist die
            // einzige Stelle, die ihre Form kennt.
            'inbound_address' => (string) (
                StudioSettings::current()->inbound_secret === null
                    ? ''
                    : InboundAddress::catchAll(
                        StudioSettings::current()->display_name ?? '',
                        StudioSettings::current()->inbound_secret,
                    )
            ),
        ];
    }
}
