<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The one thing a dashboard row offers to do about itself.
 *
 * Two kinds, both anchors: `tel` dials a contact, `link` opens the screen
 * where the decision is taken. There is deliberately no `post` — see the
 * design's Part 0. A third kind arrives with the first row that earns a
 * one-press action, not before.
 */
#[TypeScript]
class RowActionData extends Data
{
    public function __construct(
        public string $label,
        public string $kind,
        public string $target,
    ) {}

    public static function tel(string $label, ?string $phone): ?self
    {
        return $phone === null || trim($phone) === ''
            ? null
            : new self($label, 'tel', 'tel:'.preg_replace('/[^\d+]/', '', $phone));
    }

    public static function link(string $label, string $href): self
    {
        return new self($label, 'link', $href);
    }
}
