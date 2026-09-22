<?php

namespace App\Documents\Support;

use App\Documents\Enums\DocumentTemplateType;

/**
 * T-16: ready-made templates in code, offered as the starting point on
 * /dokumentvorlagen/neu.
 *
 * The block editor removed the HTML barrier and left the empty-page barrier
 * standing. A photographer clicking "Neue Vorlage" was being asked to write a
 * Werkvertrag from nothing, which is another way of saying the template system
 * was for people who already had templates.
 *
 * **A starter is a starting point, not a link.** Choosing one loads its blocks
 * into the editor; saving writes a tenant-owned first version through the
 * ordinary path. Nothing afterwards remembers which starter it came from, and
 * editing an entry in a later release reaches no template a studio has since
 * made its own — there is no id to reach through.
 *
 * No table, no migration, no seeder: this is a constant, and the same role
 * DocumentBlockCatalog and MergeFieldVocabulary already play. One source the
 * UI and the tests both read.
 *
 * Two invariants are enforced by StarterTemplateCatalogTest rather than by
 * review, because both fail silently otherwise:
 *
 * 1. every block an entry uses passes `DocumentBlockCatalog::allows()` for
 *    that entry's type, so the catalog can never offer something the server
 *    rejects on save;
 * 2. every `{{ … }}` path exists in `MergeFieldVocabulary::paths()`, so a
 *    starter can never ship a placeholder that renders empty in front of a
 *    client.
 *
 * **The wording is a starting point and not legal advice.** That sentence
 * belongs in an entry's `description`, where the studio reads it while
 * choosing — never in a body a client would receive.
 */
class StarterTemplateCatalog
{
    /**
     * @return list<array{key: string, name: string, description: string, shootKind: ?string, blocks: array{blocks: list<array{type: string, data: array<string, mixed>}>}}>
     */
    public static function for(DocumentTemplateType $type, string $register = 'formal'): array
    {
        return array_values(array_filter(
            self::all($register),
            fn (array $entry) => $entry['type'] === $type->value,
        ));
    }

    /** @return array<string, list<array<string, mixed>>> keyed by document type, for the create screen's type select */
    public static function byType(string $register = 'formal'): array
    {
        return collect(DocumentTemplateType::cases())
            ->mapWithKeys(fn (DocumentTemplateType $type) => [
                $type->value => collect(self::for($type, $register))
                    ->map(fn (array $entry) => [
                        'key' => $entry['key'],
                        'name' => $entry['name'],
                        'description' => $entry['description'],
                        'shootKind' => $entry['shootKind'],
                        'blocks' => $entry['blocks'],
                    ])
                    ->all(),
            ])
            ->all();
    }

    public static function find(string $key, string $register = 'formal'): ?array
    {
        return collect(self::all($register))->firstWhere('key', $key);
    }

    /**
     * The set the wizard writes on finish: the quote and the two mails a
     * studio will send in its first week. Variants stay out — a studio that
     * shoots no weddings should not finish onboarding with a wedding quote.
     *
     * The invoice is not in here, because which of its two variants is right
     * is not a matter of taste: a Kleinunternehmer's invoice must not show a
     * VAT line at all, and the wizard no longer asks for the VAT mode — that
     * question falls due with the first quote. See invoiceDefaults().
     *
     * @return list<string>
     */
    public static function onboardingDefaults(): array
    {
        return [
            'quote-generic',
            'mail-quote-sent',
            'mail-booking-confirmed',
        ];
    }

    /**
     * The invoice starter, written when the first invoice is drafted — by
     * then the studio has answered the VAT question at the quote gate.
     *
     * @return list<string>
     */
    public static function invoiceDefaults(string $vatMode): array
    {
        return [$vatMode === 'small_business' ? 'invoice-small-business' : 'invoice-standard'];
    }

    /**
     * @return list<array{key: string, type: string, name: string, description: string, shootKind: ?string, blocks: array{blocks: list<array{type: string, data: array<string, mixed>}>}}>
     */
    public static function all(string $register = 'formal'): array
    {
        return [
            self::quoteGeneric(),
            self::quoteWedding(),
            self::invoiceStandard(),
            self::invoiceSmallBusiness(),
            self::contractWerkvertrag(),
            self::creditNote(),
            self::widerruf(),
            ...self::mails($register),
        ];
    }

    /**
     * Ein Gerüst, kein Rechtstext: Überschriften, Reihenfolge und die Felder,
     * die eine Widerrufsbelehrung ohne das Studio nicht ausfüllen kann. Die
     * Fristen und die Formulierung stehen im Gesetz und gehören vor dem
     * ersten Versand von jemandem geprüft, der dafür haftet — deshalb Lücken
     * in eckigen Klammern statt eines Wortlauts, den hier niemand
     * verantworten kann.
     */
    private static function widerruf(): array
    {
        return [
            'key' => 'legal-widerruf',
            'type' => DocumentTemplateType::Legal->value,
            'name' => 'Widerrufsbelehrung',
            'description' => 'Gerüst mit den Pflichtangaben. Die Formulierung ist kein Rechtsrat und gehört vor dem ersten Versand geprüft.',
            'shootKind' => null,
            'blocks' => ['blocks' => [
                self::header('Widerrufsrecht', 2),
                self::paragraph('[Frist und Beginn eintragen.]'),
                self::header('Ausübung des Widerrufs', 3),
                self::paragraph('{{ studio.name }}, {{ studio.street }}, {{ studio.postcode }} {{ studio.city }}'),
                self::paragraph('{{ studio.representative }}'),
                self::header('Folgen des Widerrufs', 3),
                self::paragraph('[Rückzahlung und Fristen eintragen.]'),
                self::delimiter(),
                self::header('Muster-Widerrufsformular', 3),
                self::paragraph('[Formular eintragen: Empfänger, Vertrag, Datum, Name, Anschrift, Unterschrift.]'),
            ]],
        ];
    }

    // --- Angebot ---------------------------------------------------------

    private static function quoteGeneric(): array
    {
        return [
            'key' => 'quote-generic',
            'type' => DocumentTemplateType::Quote->value,
            'name' => 'Angebot',
            'description' => 'Leistungsumfang, Gültigkeit und Umsatzsteuerhinweis. Preise und Positionen kommen aus dem Angebot selbst.',
            'shootKind' => null,
            'blocks' => ['blocks' => [
                self::header('Angebot {{ project.name }}'),
                self::paragraph('Guten Tag {{ contact.name }},'),
                self::paragraph('vielen Dank für Ihre Anfrage. Hier mein Angebot:'),
                self::header('Leistungen', 3),
                self::paragraph('Die einzelnen Positionen und Preise stehen in der Übersicht unter diesem Text.'),
                self::header('Gültigkeit', 3),
                self::paragraph('Dieses Angebot gilt bis {{ quote.validUntil }}.'),
                self::header('Umsatzsteuer', 3),
                self::paragraph('{{ quote.vatNote }}'),
                self::delimiter(),
                self::paragraph('Bei Fragen erreichen Sie mich jederzeit.'),
            ]],
        ];
    }

    private static function quoteWedding(): array
    {
        return [
            'key' => 'quote-wedding',
            'type' => DocumentTemplateType::Quote->value,
            'name' => 'Angebot — Hochzeit',
            'description' => 'Wie das allgemeine Angebot, mit Ablauf am Hochzeitstag und den üblichen enthaltenen Leistungen.',
            'shootKind' => 'Hochzeitstag',
            'blocks' => ['blocks' => [
                self::header('Angebot {{ project.name }}'),
                self::paragraph('Guten Tag {{ contact.name }},'),
                self::paragraph('schön, dass Sie an mich gedacht haben. Hier mein Angebot für Ihren Hochzeitstag am {{ shoot.date }}.'),
                self::header('Begleitung am Tag', 3),
                self::paragraph('Ich begleite Sie über den vereinbarten Zeitraum — Vorbereitungen, Trauung, Feier. Den genauen Ablauf stimmen wir vorher gemeinsam ab.'),
                self::header('Enthalten', 3),
                self::list([
                    'Vorgespräch und Ablaufplanung',
                    'Bildauswahl und Bearbeitung',
                    'Online-Galerie zum Ansehen und Herunterladen',
                ]),
                self::header('Gültigkeit', 3),
                self::paragraph('Dieses Angebot gilt bis {{ quote.validUntil }}. Der Termin ist erst mit Annahme und Anzahlung fest reserviert.'),
                self::header('Umsatzsteuer', 3),
                self::paragraph('{{ quote.vatNote }}'),
            ]],
        ];
    }

    // --- Vertrag ---------------------------------------------------------

    private static function contractWerkvertrag(): array
    {
        return [
            'key' => 'contract-werkvertrag',
            'type' => DocumentTemplateType::Contract->value,
            'name' => 'Fotovertrag (Werkvertrag)',
            'description' => 'Gerüst mit Nutzungsrechten, Ausfall und Rücktritt. Ausgangspunkt, keine Rechtsberatung — vor dem ersten Einsatz prüfen lassen.',
            'shootKind' => null,
            'blocks' => ['blocks' => [
                self::header('Fotovertrag'),

                self::header('1. Parteien', 3),
                self::paragraph('{{ studio.name }}, {{ studio.street }}, {{ studio.postcode }} {{ studio.city }} — nachfolgend Fotograf.'),
                self::paragraph('{{ contact.name }}, {{ contact.street }}, {{ contact.postcode }} {{ contact.city }} — nachfolgend Auftraggeber.'),

                self::header('2. Leistung', 3),
                self::paragraph('Der Fotograf erstellt Aufnahmen im Rahmen von {{ project.name }}. Umfang und Ablauf ergeben sich aus dem angenommenen Angebot.'),

                self::header('3. Termine', 3),
                self::paragraph('Termin und Zeitrahmen sind im Angebot festgehalten. Verschiebungen bedürfen der Abstimmung beider Seiten in Textform.'),

                self::header('4. Vergütung und Zahlung', 3),
                self::paragraph('Es gilt die im Angebot vereinbarte Vergütung. Die Anzahlung wird mit Vertragsschluss fällig, der Restbetrag nach dem im Zahlungsplan genannten Termin.'),

                self::header('5. Nutzungsrechte', 3),
                self::paragraph('Der Auftraggeber erhält ein einfaches, räumlich und zeitlich unbeschränktes Nutzungsrecht an den gelieferten Bildern für private Zwecke.'),
                self::paragraph('Eine gewerbliche Nutzung, die Weitergabe an Dritte zur Veröffentlichung sowie der Weiterverkauf bedürfen einer gesonderten Vereinbarung.'),
                self::paragraph('Bearbeitungen, die den Bildcharakter verändern — Zuschnitt über das Format hinaus, Filter, Retusche —, sind nur nach Absprache zulässig. Bei Veröffentlichung ist der Fotograf zu nennen.'),
                self::paragraph('Der Fotograf bleibt Urheber und darf die Bilder zur eigenen Werbung verwenden, sofern der Auftraggeber dem nicht in Textform widerspricht.'),

                self::header('6. Bildauswahl und Lieferung', 3),
                self::paragraph('Der Fotograf trifft die Auswahl der zu bearbeitenden Bilder nach gestalterischen Gesichtspunkten. Ein Anspruch auf unbearbeitete Dateien besteht nicht.'),
                self::paragraph('Die Lieferung erfolgt über eine Online-Galerie innerhalb der vereinbarten Frist.'),

                self::header('7. Ausfall und Rücktritt', 3),
                self::paragraph('Kann der Fotograf den Termin aus einem von ihm nicht zu vertretenden Grund nicht wahrnehmen, bemüht er sich um eine Vertretung. Gelingt das nicht, werden bereits gezahlte Beträge erstattet; weitergehende Ansprüche bestehen nicht.'),
                self::paragraph('Tritt der Auftraggeber zurück, bleibt die Anzahlung als Ausgleich für den reservierten Termin einbehalten.'),

                self::header('8. Datenschutz', 3),
                self::paragraph('Personenbezogene Daten werden ausschließlich zur Durchführung dieses Vertrags verarbeitet. Einzelheiten regelt die Datenschutzerklärung des Fotografen.'),

                self::header('9. Schlussbestimmungen', 3),
                self::paragraph('Änderungen bedürfen der Textform. Sollte eine Bestimmung unwirksam sein, bleibt der übrige Vertrag wirksam.'),
            ]],
        ];
    }

    // --- Rechnung --------------------------------------------------------

    // --- Rechnung --------------------------------------------------------

    private static function invoiceStandard(): array
    {
        return [
            'key' => 'invoice-standard',
            'type' => DocumentTemplateType::Invoice->value,
            'name' => 'Rechnung',
            'description' => 'Anschreiben zur Rechnung; Pflichtangaben und Summen liefert das Layout. Ausgangspunkt, keine Steuerberatung.',
            'shootKind' => null,
            'blocks' => ['blocks' => [
                self::paragraph('Guten Tag {{ contact.name }},'),
                self::paragraph('vielen Dank für den Auftrag. Die Aufstellung finden Sie unten.'),
            ]],
        ];
    }

    private static function invoiceSmallBusiness(): array
    {
        return [
            'key' => 'invoice-small-business',
            'type' => DocumentTemplateType::Invoice->value,
            'name' => 'Rechnung — Kleinunternehmer',
            'description' => 'Anschreiben ohne Umsatzsteuerausweis; der §-19-Hinweis kommt aus dem Layout. Ausgangspunkt, keine Steuerberatung.',
            'shootKind' => null,
            'blocks' => ['blocks' => [
                self::paragraph('Guten Tag {{ contact.name }},'),
                self::paragraph('vielen Dank für den Auftrag. Die Aufstellung finden Sie unten.'),
            ]],
        ];
    }

    // --- Gutschrift ------------------------------------------------------

    private static function creditNote(): array
    {
        return [
            'key' => 'credit-note',
            'type' => DocumentTemplateType::CreditNote->value,
            'name' => 'Gutschrift',
            'description' => 'Anschreiben zur Gutschrift; Bezugszeile und Summen liefert das Layout. Ausgangspunkt, keine Steuerberatung.',
            'shootKind' => null,
            'blocks' => ['blocks' => [
                self::paragraph('Guten Tag {{ contact.name }},'),
                self::paragraph('Grund der Gutschrift: {{ creditNote.reason }}'),
                self::paragraph('Der Betrag wird auf das uns bekannte Konto erstattet.'),
            ]],
        ];
    }

    // --- E-Mail ----------------------------------------------------------
    //
    // Short, and none of them signs off: T-06 appends the studio's signature
    // and legal footer to every outgoing mail, so a starter that repeated
    // either would send it twice.

    // #439: the mails reach the client, so they come in the studio's register;
    // the documents stay formal until #438 decides otherwise.
    private static function mails(string $register): array
    {
        $du = $register === 'informal';
        $greeting = self::paragraph($du ? 'Hallo {{ contact.name }},' : 'Guten Tag {{ contact.name }},');
        $openQuestions = self::paragraph($du ? 'Melde dich gern, wenn etwas offen ist.' : 'Melden Sie sich gern, wenn etwas offen ist.');

        return [
            [
                'key' => 'mail-quote-sent',
                'type' => DocumentTemplateType::Mail->value,
                'name' => 'Angebot verschickt',
                'description' => 'Begleitmail zum Angebot.',
                'shootKind' => null,
                'blocks' => ['blocks' => [
                    $greeting,
                    self::paragraph('anbei mein Angebot für {{ project.name }}. Es gilt bis {{ quote.validUntil }}.'),
                    $openQuestions,
                ]],
            ],
            [
                'key' => 'mail-booking-confirmed',
                'type' => DocumentTemplateType::Mail->value,
                'name' => 'Buchung bestätigt',
                'description' => 'Bestätigung nach Annahme und Anzahlung.',
                'shootKind' => null,
                'blocks' => ['blocks' => [
                    $greeting,
                    self::paragraph('der Termin für {{ project.name }} ist fest eingetragen. Ich freue mich darauf.'),
                    self::paragraph('Vorher melde ich mich rechtzeitig zur Abstimmung.'),
                ]],
            ],
            [
                'key' => 'mail-invoice-sent',
                'type' => DocumentTemplateType::Mail->value,
                'name' => 'Rechnung verschickt',
                'description' => 'Begleitmail zur Rechnung.',
                'shootKind' => null,
                'blocks' => ['blocks' => [
                    $greeting,
                    self::paragraph('anbei die Rechnung {{ invoice.number }} über {{ invoice.gross }} für {{ project.name }}. Zahlbar bis {{ invoice.dueOn }}.'),
                    $openQuestions,
                ]],
            ],
            [
                'key' => 'mail-payment-reminder',
                'type' => DocumentTemplateType::Mail->value,
                'name' => 'Zahlungserinnerung',
                'description' => 'Freundliche Erinnerung an eine offene Rate.',
                'shootKind' => null,
                'blocks' => ['blocks' => [
                    $greeting,
                    self::paragraph('die Rechnung {{ invoice.number }} über {{ invoice.gross }} war am {{ invoice.dueOn }} fällig und ist noch offen.'),
                    self::paragraph($du
                        ? 'Falls sich das überschnitten hat, betrachte diese Nachricht bitte als gegenstandslos.'
                        : 'Falls sich das überschnitten hat, betrachten Sie diese Nachricht bitte als gegenstandslos.'),
                ]],
            ],
            [
                'key' => 'mail-gallery-online',
                'type' => DocumentTemplateType::Mail->value,
                'name' => 'Galerie online',
                'description' => 'Benachrichtigung, dass die Bilder bereitstehen.',
                'shootKind' => null,
                'blocks' => ['blocks' => [
                    $greeting,
                    self::paragraph($du
                        ? 'die Bilder zu {{ project.name }} sind fertig und stehen in deiner Galerie bereit.'
                        : 'die Bilder zu {{ project.name }} sind fertig und stehen in Ihrer Galerie bereit.'),
                    self::paragraph($du ? 'Das Passwort hast du separat erhalten.' : 'Das Passwort haben Sie separat erhalten.'),
                ]],
            ],
        ];
    }

    // --- block helpers ---------------------------------------------------

    private static function header(string $text, int $level = 2): array
    {
        return ['type' => 'header', 'data' => ['text' => $text, 'level' => $level]];
    }

    private static function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'data' => ['text' => $text]];
    }

    /** @param list<string> $items */
    private static function list(array $items, string $style = 'unordered'): array
    {
        return ['type' => 'list', 'data' => ['style' => $style, 'items' => $items]];
    }

    private static function delimiter(): array
    {
        return ['type' => 'delimiter', 'data' => []];
    }

    /** @param list<list<string>> $content */
    private static function table(array $content, bool $withHeadings = false): array
    {
        return ['type' => 'table', 'data' => ['withHeadings' => $withHeadings, 'content' => $content]];
    }
}
