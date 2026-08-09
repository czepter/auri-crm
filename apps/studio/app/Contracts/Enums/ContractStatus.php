<?php

namespace App\Contracts\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractStatus: string
{
    case Pending = 'pending';
    case Rendered = 'rendered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Wird erstellt',
            self::Rendered => 'Erstellt',
            self::Failed => 'Fehlgeschlagen',
        };
    }
}
