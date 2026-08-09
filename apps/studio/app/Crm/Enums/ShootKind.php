<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What kind of occasion a shoot is. Finer-grained than ProjectType: one wedding
 * project routinely holds an engagement shoot, the wedding day and the
 * reception, separated by as much as a year.
 */
#[TypeScript]
enum ShootKind: string implements HasLabel
{
    case Engagement = 'engagement';
    case Wedding = 'wedding';
    case Reception = 'reception';
    case Portrait = 'portrait';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Engagement => 'Verlobungsshooting',
            self::Wedding => 'Hochzeitstag',
            self::Reception => 'Feier',
            self::Portrait => 'Portrait',
            self::Other => 'Sonstige',
        };
    }
}
