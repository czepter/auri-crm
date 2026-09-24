<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lives on Project from capture on. Mockup 03 collects it months before a
 * booking and for the many leads that never convert at all, and a project's
 * nature does not change when it does. Deviation from vision §3.1, recorded in
 * the P1b spec §2.2.
 *
 * Shoot.kind stays the finer-grained field: a wedding project has shoots of
 * kind engagement, wedding and reception.
 */
#[TypeScript]
enum ProjectType: string implements HasLabel
{
    case Wedding = 'wedding';
    case Portrait = 'portrait';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wedding => 'Hochzeit',
            self::Portrait => 'Portrait',
            self::Other => 'Sonstige',
        };
    }
}
