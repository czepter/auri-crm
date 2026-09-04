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
        // §14 UStG wants the recipient's full name *and* address, so the
        // three billing-address columns M-06 added to contacts are part of
        // the vocabulary rather than something an invoice template has to
        // ask the studio to retype.
        'contact.street' => 'Gartenweg 4',
        'contact.postcode' => '22765',
        'contact.city' => 'Hamburg',
        'job.bookedAt' => '14.06.2026',
        // T-17's starters need the occasion's own date and the two figures a
        // quote states about itself. Resolved from the project's primary
        // shoot and its latest sent quote — see DocumentMergeDataBuilder.
        'shoot.date' => '14.06.2026',
        'quote.validUntil' => '30.09.2026',
        'quote.vatNote' => 'Alle Preise verstehen sich zzgl. 19 % Umsatzsteuer.',
        // C-17 (#253): the "Galerie online" letter carries link, password and
        // code, so the client does not hunt through three messages. Whoever
        // forwards that mail hands on the access — for a gallery that is
        // shared by messenger anyway, that is the normal case and not a new
        // hole.
        'gallery.title' => 'Meier & Kowalski — Hochzeitstag',
        'gallery.link' => 'https://bilder.nordlicht.example/g/hochzeit-meier-kowalski',
        'gallery.password' => 'basthorst2026',
        'gallery.downloadPin' => '4821',
        'gallery.expiresOn' => '28.08.2026',
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
        'studio.bankHolder' => 'Atelier Nordlicht Fotografie GmbH',
        'studio.bankIban' => 'DE02 1203 0000 0000 2020 51',
        'studio.bankBic' => 'BYLADEM1001',
        'settings.widerrufText' => 'Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen zu widerrufen.',
        // M-06 / T-15: the §14 UStG particulars an invoice carries and no
        // other document does. Every one of these resolves off the issued
        // installment; on any other document type they resolve to empty,
        // which is why an invoice template is the only place they belong.
        'invoice.number' => 'RE-2026-17',
        'invoice.date' => '15.08.2026',
        'invoice.servicePeriod' => '14.06.2026',
        'invoice.description' => 'Hochzeitsreportage — 1. Rate',
        'invoice.net' => '1.260,50 €',
        'invoice.vatRate' => '19 %',
        'invoice.vat' => '239,50 €',
        'invoice.gross' => '1.500,00 €',
        // Prints only under the small-business rule; the sample carries the
        // §19 sentence so a preview shows what that case looks like rather
        // than an empty line nobody can judge.
        'invoice.vatNote' => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
        'invoice.dueOn' => '29.08.2026',
        'invoice.paymentTerm' => 'Zahlbar bis 29.08.2026 ohne Abzug.',
    ];

    /** @return list<string> Every documented dot-path, e.g. "project.name". */
    public static function paths(): array
    {
        return array_keys(self::SAMPLE);
    }

    /**
     * Every `{{ path }}` in $body that this vocabulary does not know — E-10's
     * "an unresolved placeholder blocks sending, and names the field".
     *
     * MergeFieldResolver replaces an unknown path with an empty string, which
     * is the right behaviour at render time and the wrong one to discover at
     * read time: a typo'd field does not shout, it silently deletes a
     * sentence. This is how a caller finds out before that happens.
     *
     * @return list<string>
     */
    public static function unknownPathsIn(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/', $body, $matches);

        return array_values(array_unique(array_diff($matches[1] ?? [], self::paths())));
    }

    /**
     * The flat path => sample map behind sample(). The editor shows these values
     * in its merge-field chips so a paragraph reads as prose rather than as
     * braces, and paints them into its instant preview.
     *
     * @return array<string, string>
     */
    public static function samples(): array
    {
        return self::SAMPLE;
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
