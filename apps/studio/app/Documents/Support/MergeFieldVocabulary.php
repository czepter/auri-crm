<?php

namespace App\Documents\Support;

use Illuminate\Support\Arr;

/**
 * Every merge-field path this app resolves, named once — the single source
 * both docs/documents.md and DocumentTemplateController::preview() read
 * from, so the vocabulary a template author sees and the one a preview
 * renders against can never drift apart. Keep this list and
 * DocumentMergeDataBuilder::fromProject()'s array shape in sync: a path
 * here with nothing on that side resolves to an empty string forever, and a
 * path on that side with nothing here is undocumented.
 */
class MergeFieldVocabulary
{
    /** @var array<string, string> dot-path => a realistic sample value */
    private const SAMPLE = [
        'project.name' => 'Hochzeit Meier & Kowalski',
        'project.type' => 'Hochzeit',
        'contact.name' => 'Lena Meier',
        'contact.email' => 'lena@example.test',
        'job.bookedAt' => '14.06.2026',
        'studio.name' => 'Atelier Nordlicht Fotografie GmbH',
        'studio.legalForm' => 'GmbH',
        'studio.street' => 'Beispielstraße 12',
        'studio.postcode' => '20095',
        'studio.city' => 'Hamburg',
        'studio.country' => 'Deutschland',
        'studio.taxNumber' => '22/123/45678',
        'studio.vatId' => 'DE123456789',
        'studio.registerNumber' => 'HRB 12345',
        'studio.registerCourt' => 'Amtsgericht Hamburg',
        'studio.representative' => 'Alex Nordlicht',
        'studio.website' => 'https://nordlicht.example',
        'studio.logoPath' => 'branding/sample/logo.png',
        'settings.widerrufText' => 'Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen zu widerrufen.',
    ];

    /** @return list<string> Every documented dot-path, e.g. "project.name". */
    public static function paths(): array
    {
        return array_keys(self::SAMPLE);
    }

    /**
     * A complete, dot-addressable sample dataset — every path in the
     * vocabulary resolves against this with nothing left over, which is
     * what makes a preview built from it prove "no unresolved placeholder"
     * rather than merely exercise a few fields.
     *
     * @return array<string, mixed>
     */
    public static function sample(): array
    {
        $data = [];

        foreach (self::SAMPLE as $path => $value) {
            Arr::set($data, $path, $value);
        }

        return $data;
    }
}
