<?php

namespace App\Documents\Support;

use App\Documents\Enums\DocumentTemplateType;

/**
 * Which blocks each document type may use — the single source the inserter
 * palette and save validation both read, so what a user is offered and what
 * the server accepts can never drift apart. Same role MergeFieldVocabulary
 * plays for merge paths.
 *
 * A page break has no meaning in an email and a two-column layout survives
 * email clients badly, so the allow-list is per type rather than one global
 * set filtered in the UI.
 *
 * Phase 1 lists only implemented blocks. Adding a block here without a
 * serializer case in resources/js/admin/documents/blocks/serialize.ts would
 * put an entry in the palette that renders to nothing.
 */
class DocumentBlockCatalog
{
    /**
     * type => [group, label, allowed document types, inline?]
     *
     * @var array<string, array{group: string, label: string, types: list<string>, inline?: bool}>
     */
    private const BLOCKS = [
        'paragraph' => ['group' => 'text', 'label' => 'Absatz', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'header' => ['group' => 'text', 'label' => 'Überschrift', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'list' => ['group' => 'text', 'label' => 'Liste', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'quote' => ['group' => 'text', 'label' => 'Zitat', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'delimiter' => ['group' => 'text', 'label' => 'Trenner', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'table' => ['group' => 'layout', 'label' => 'Tabelle', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'columns' => ['group' => 'layout', 'label' => '2 Spalten', 'types' => ['quote', 'invoice', 'credit_note']],
        'image' => ['group' => 'layout', 'label' => 'Bild', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'callout' => ['group' => 'layout', 'label' => 'Hinweis', 'types' => ['quote', 'invoice', 'credit_note', 'mail']],
        'spacer' => ['group' => 'layout', 'label' => 'Abstand', 'types' => ['quote', 'invoice', 'credit_note']],
        'pagebreak' => ['group' => 'layout', 'label' => 'Seitenumbruch', 'types' => ['quote', 'invoice', 'credit_note']],

        // Was ein Angebot über Fließtext hinaus braucht. Die Zeilenbausteine
        // teilen sich ein Werkzeug (resources/js/admin/documents/blocks/
        // rowSpecs.ts); unterschiedlich sind nur ihre Spalten.
        'checklist' => ['group' => 'print', 'label' => 'Ankreuzliste', 'types' => ['quote']],
        'finePrint' => ['group' => 'print', 'label' => 'Kleingedrucktes', 'types' => ['quote', 'invoice', 'credit_note']],
        'usageRights' => ['group' => 'print', 'label' => 'Nutzungsrechte', 'types' => ['quote']],
        'cancellation' => ['group' => 'print', 'label' => 'Ausfallstaffel', 'types' => ['quote']],

        'lineItems' => ['group' => 'data', 'label' => 'Positionen', 'types' => ['quote', 'invoice', 'credit_note']],
        'paymentPlan' => ['group' => 'data', 'label' => 'Zahlungsplan', 'types' => ['quote', 'invoice', 'credit_note']],
        'deliverables' => ['group' => 'data', 'label' => 'Lieferumfang', 'types' => ['quote']],
        'schedule' => ['group' => 'data', 'label' => 'Ablaufplan', 'types' => ['quote']],
        'mergeField' => ['group' => 'data', 'label' => 'Feld', 'types' => ['quote', 'invoice', 'credit_note', 'mail'], 'inline' => true],
    ];

    /**
     * #480: the letter text of a quote or an invoice — prose and merge fields,
     * no block a layout would have to make room for.
     *
     * @return list<array{type: string, group: string, label: string, inline: bool}>
     */
    public static function letter(): array
    {
        return array_values(array_filter(
            self::for(DocumentTemplateType::Quote),
            fn (array $entry) => $entry['group'] === 'text' || $entry['inline'],
        ));
    }

    /**
     * The palette for one document type, in the order the inserter shows it.
     *
     * @return list<array{type: string, group: string, label: string, inline: bool}>
     */
    public static function for(DocumentTemplateType $type): array
    {
        $palette = [];

        foreach (self::BLOCKS as $blockType => $definition) {
            if (! in_array($type->value, $definition['types'], true)) {
                continue;
            }

            $palette[] = [
                'type' => $blockType,
                'group' => $definition['group'],
                'label' => $definition['label'],
                'inline' => $definition['inline'] ?? false,
            ];
        }

        return $palette;
    }

    /**
     * The same palette, plus the blocks this type may not have — each with
     * the reason, in the words the inserter shows.
     *
     * C-16 (#252): a disallowed entry stands dimmed rather than missing. What
     * is silently absent reads as a bug, and the operator goes looking for
     * the block they saw on the other template. allows() still decides on the
     * server; this only stops the screen lying about why.
     *
     * @return list<array{type: string, group: string, label: string, inline: bool, allowed: bool, only: ?string}>
     */
    public static function paletteFor(DocumentTemplateType $type): array
    {
        $palette = [];

        foreach (self::BLOCKS as $blockType => $definition) {
            $allowed = in_array($type->value, $definition['types'], true);

            $palette[] = [
                'type' => $blockType,
                'group' => $definition['group'],
                'label' => $definition['label'],
                'inline' => $definition['inline'] ?? false,
                'allowed' => $allowed,
                'only' => $allowed ? null : implode(' und ', array_map(
                    fn (string $value) => DocumentTemplateType::from($value)->label(),
                    $definition['types'],
                )),
            ];
        }

        return $palette;
    }

    /**
     * May this document type persist a block of this type?
     *
     * rawHtml is allowed everywhere and deliberately absent from the palette:
     * it is what a pre-block-editor body imports into, so refusing it would
     * make every legacy template unsaveable the moment someone opened it.
     */
    public static function allows(DocumentTemplateType $type, string $blockType): bool
    {
        if ($blockType === 'rawHtml') {
            return true;
        }

        $definition = self::BLOCKS[$blockType] ?? null;

        if ($definition === null || ($definition['inline'] ?? false)) {
            return false;
        }

        return in_array($type->value, $definition['types'], true);
    }
}
