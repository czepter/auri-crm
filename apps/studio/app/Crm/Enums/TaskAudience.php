<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who a task is about (vision §3.3): work the studio owes itself, or work that
 * needs something from the client.
 *
 * A real field rather than a convention, because the two read differently on
 * every screen that shows them — "2× erinnert, keine Reaktion" is only a
 * sentence about a client-facing task.
 *
 * It never stands in for InternalNote, which is a separate model with no
 * portal-facing route at all. This says who a task concerns; that one is a
 * structural guarantee about what a client can reach.
 */
#[TypeScript]
enum TaskAudience: string implements HasLabel
{
    case Internal = 'internal';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Intern',
            self::Client => 'Kunde',
        };
    }
}
