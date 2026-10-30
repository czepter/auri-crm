<?php

namespace App\Portal\Support;

use App\Models\StudioSettings;

/** The studio's four legal texts and where the portal sends a client to read each. */
final class LegalTexts
{
    public const ALL = ['impressum', 'datenschutz', 'agb', 'widerruf'];

    /**
     * Wohin ein Rechtstext im Portal zeigt: auf die fremde Adresse, wenn eine
     * hinterlegt ist, sonst auf die eigene Seite, wenn eine Vorlage gewählt
     * wurde. Das Impressum hat immer eine Seite — es wird aus den
     * Studio-Daten gezeichnet, wenn nichts anderes gesetzt ist.
     */
    public static function url(string $text): ?string
    {
        $row = StudioSettings::current();

        return match (true) {
            filled($row->{"portal_legal_{$text}_url"} ?? null) => $row->{"portal_legal_{$text}_url"},
            filled($row->{"portal_legal_{$text}_family"} ?? null) => "/rechtstexte/{$text}",
            $text === 'impressum' => '/impressum',
            default => null,
        };
    }

    /** Die Vorlagen-Familie hinter einem Rechtstext, sofern eine gewählt ist. */
    public static function family(string $text): ?string
    {
        return StudioSettings::current()->{"portal_legal_{$text}_family"} ?? null;
    }

    /** Ob AGB oder Widerrufsbelehrung dem Angebot beiliegen. */
    public static function attach(string $text): bool
    {
        return (bool) (StudioSettings::current()->{"portal_legal_{$text}_attach"} ?? false);
    }

    /**
     * Was dem Angebot beiliegt: je eingeschaltetem Text seine Adresse, wie
     * url() sie liefert — relativ, wenn der Text im Portal liegt.
     *
     * @return array<string, string>
     */
    public static function attachedUrls(): array
    {
        return collect(['agb', 'widerruf'])
            ->filter(fn (string $text) => self::attach($text) && self::url($text) !== null)
            ->mapWithKeys(fn (string $text) => [$text => self::url($text)])
            ->all();
    }

    /** Die eingetragene fremde Adresse — was das Portal daraus macht, sagt url(). */
    public static function externalUrl(string $text): ?string
    {
        return StudioSettings::current()->{"portal_legal_{$text}_url"} ?? null;
    }
}
