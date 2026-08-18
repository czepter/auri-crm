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
        'paragraph' => ['group' => 'text', 'label' => 'Absatz', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'header' => ['group' => 'text', 'label' => 'Überschrift', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'list' => ['group' => 'text', 'label' => 'Liste', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'quote' => ['group' => 'text', 'label' => 'Zitat', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'delimiter' => ['group' => 'text', 'label' => 'Trenner', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'table' => ['group' => 'layout', 'label' => 'Tabelle', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'columns' => ['group' => 'layout', 'label' => '2 Spalten', 'types' => ['contract', 'quote', 'invoice']],
        'image' => ['group' => 'layout', 'label' => 'Bild', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'callout' => ['group' => 'layout', 'label' => 'Hinweis', 'types' => ['contract', 'quote', 'invoice', 'mail']],
        'spacer' => ['group' => 'layout', 'label' => 'Abstand', 'types' => ['contract', 'quote', 'invoice']],
        'mergeField' => ['group' => 'data', 'label' => 'Feld', 'types' => ['contract', 'quote', 'invoice', 'mail'], 'inline' => true],
    ];

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
