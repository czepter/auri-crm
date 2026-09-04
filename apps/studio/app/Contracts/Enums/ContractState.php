<?php

namespace App\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where one Fassung stands among its siblings — separate from
 * ContractStatus, which is only about whether the PDF exists yet.
 *
 * Exactly one Valid row per project: the signed Fassung keeps that place
 * until the next one is sent, which is the moment it becomes Superseded.
 */
#[TypeScript]
enum ContractState: string
{
    case Draft = 'draft';
    case Valid = 'valid';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Valid => 'Gültig',
            self::Superseded => 'Ersetzt',
        };
    }
}
