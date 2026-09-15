<?php

namespace App\Payments\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * #408: what a Storno says about the work, not about the document. Both
 * kinds retire the same invoice; they disagree about whether the studio is
 * still owed the amount. Correction hands it back to the plan as a fresh
 * scheduled row, Void takes it off the total.
 */
#[TypeScript]
enum CancellationKind: string
{
    case Correction = 'correction';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Correction => 'Korrektur',
            self::Void => 'Wegfall',
        };
    }
}
