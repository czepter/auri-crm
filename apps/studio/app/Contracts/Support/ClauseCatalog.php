<?php

namespace App\Contracts\Support;

use App\Documents\Models\DocumentTemplate;

/**
 * Die Klauseln, aus denen der Schnellbau einen Vertrag zusammensetzt —
 * dieselbe Rolle wie StarterTemplateCatalog für Vorlagen: eine Konstante,
 * die UI und Test gemeinsam lesen, keine Tabelle.
 *
 * `on` ist der Vorschlag, nicht die Pflicht: jede Klausel lässt sich
 * abschalten, auch die, die der Katalog anhakt.
 *
 * Eine gespeicherte Vertragsvorlage sticht den Katalog: trägt sie eine
 * Klausel mit derselben Überschrift, gilt ihr Text.
 *
 * Die Wortlaute sind ein Ausgangspunkt, keine Rechtsberatung.
 */
class ClauseCatalog
{
    /** @return list<array{key: string, group: string, title: string, text: string, on: bool}> */
    public static function all(): array
    {
        return [
            self::clause('parties', 'auftrag', 'Parteien', '{{ studio.name }}, {{ studio.street }}, {{ studio.postcode }} {{ studio.city }} — nachfolgend Fotograf. {{ contact.name }}, {{ contact.street }}, {{ contact.postcode }} {{ contact.city }} — nachfolgend Auftraggeber.'),
            self::clause('scope', 'auftrag', 'Leistung', 'Der Fotograf erstellt Aufnahmen im Rahmen von {{ project.name }}. Umfang und Ablauf ergeben sich aus dem angenommenen Angebot.'),
            self::clause('dates', 'auftrag', 'Termine', 'Gebucht sind folgende Termine: {{ shoot.list }}. Zeitrahmen und Ablauf sind im Angebot festgehalten. Verschiebungen bedürfen der Abstimmung beider Seiten in Textform.'),
            self::clause('fee', 'auftrag', 'Vergütung und Zahlung', 'Es gilt die im Angebot vereinbarte Vergütung. Zahlungsplan: {{ payment.plan }}. Die Anzahlung ist bis {{ payment.depositDue }} fällig.'),

            self::clause('usage', 'nutzung', 'Nutzungsrechte', 'Der Auftraggeber erhält ein einfaches, räumlich und zeitlich unbeschränktes Nutzungsrecht an den gelieferten Bildern für private Zwecke.'),
            self::clause('commercial', 'nutzung', 'Gewerbliche Nutzung', 'Eine gewerbliche Nutzung, die Weitergabe an Dritte zur Veröffentlichung sowie der Weiterverkauf bedürfen einer gesonderten Vereinbarung.', on: false),
            self::clause('editing', 'nutzung', 'Bearbeitung und Nennung', 'Bearbeitungen, die den Bildcharakter verändern, sind nur nach Absprache zulässig. Bei Veröffentlichung ist der Fotograf zu nennen.'),
            self::clause('ownWork', 'nutzung', 'Eigenwerbung', 'Der Fotograf bleibt Urheber und darf die Bilder zur eigenen Werbung verwenden, sofern der Auftraggeber dem nicht in Textform widerspricht.'),
            self::clause('ai', 'nutzung', 'KI-Training', 'Die Bilder dürfen weder zum Training noch zur Erzeugung KI-generierter Inhalte verwendet werden.', on: false),

            self::clause('selection', 'lieferung', 'Bildauswahl', 'Der Fotograf trifft die Auswahl der zu bearbeitenden Bilder nach gestalterischen Gesichtspunkten. Ein Anspruch auf unbearbeitete Dateien besteht nicht.'),
            self::clause('delivery', 'lieferung', 'Lieferung', 'Die Lieferung erfolgt über eine Online-Galerie innerhalb der vereinbarten Frist.'),
            self::clause('revisions', 'lieferung', 'Korrekturschleifen', 'Eine Korrekturschleife auf die gelieferte Auswahl ist enthalten. Weitere Schleifen werden nach Aufwand berechnet.', on: false),

            self::clause('absence', 'ausfall', 'Ausfall', 'Kann der Fotograf den Termin aus einem von ihm nicht zu vertretenden Grund nicht wahrnehmen, bemüht er sich um eine Vertretung. Gelingt das nicht, werden bereits gezahlte Beträge erstattet; weitergehende Ansprüche bestehen nicht.'),
            self::clause('withdrawal', 'ausfall', 'Rücktritt', 'Tritt der Auftraggeber zurück, bleibt die Anzahlung als Ausgleich für den reservierten Termin einbehalten.'),
            self::clause('lateFee', 'ausfall', 'Zahlungsverzug', 'Nach Ablauf des Zahlungsziels werden Verzugszinsen nach § 288 BGB berechnet.', on: false),

            self::clause('privacy', 'recht', 'Datenschutz', 'Personenbezogene Daten werden ausschließlich zur Durchführung dieses Vertrags verarbeitet. Einzelheiten regelt die Datenschutzerklärung des Fotografen.'),
            self::clause('final', 'recht', 'Schlussbestimmungen', 'Änderungen bedürfen der Textform. Sollte eine Bestimmung unwirksam sein, bleibt der übrige Vertrag wirksam.'),
        ];
    }

    /**
     * Der Katalog, überschrieben von den Klauseln der Vorlage.
     *
     * @return list<array{key: string, group: string, title: string, text: string, on: bool}>
     */
    public static function forTemplate(?DocumentTemplate $template): array
    {
        $fromTemplate = collect($template?->blocks['blocks'] ?? [])
            ->where('type', 'clause')
            ->mapWithKeys(fn (array $block) => [
                mb_strtolower(strip_tags((string) ($block['data']['title'] ?? ''))) => (string) ($block['data']['text'] ?? ''),
            ])
            ->filter(fn (string $text, string $title) => $title !== '' && $text !== '');

        return collect(self::all())
            ->map(fn (array $clause) => $fromTemplate->has(mb_strtolower($clause['title']))
                ? [...$clause, 'text' => $fromTemplate->get(mb_strtolower($clause['title']))]
                : $clause)
            ->all();
    }

    /** @return array{key: string, group: string, title: string, text: string, on: bool} */
    private static function clause(string $key, string $group, string $title, string $text, bool $on = true): array
    {
        return ['key' => $key, 'group' => $group, 'title' => $title, 'text' => $text, 'on' => $on];
    }
}
