<?php

namespace App\Engagement\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What became of one inbound delivery.
 *
 * Threaded is the happy path and the only one that ever writes a Message.
 * Everything else is visible and waiting for a human — never dropped, which
 * is the whole reason `inbound_messages` holds a row for every delivery
 * rather than only for the ones that worked.
 */
#[TypeScript]
enum InboundMessageState: string
{
    case Threaded = 'threaded';
    case Triage = 'triage';
    case Quarantined = 'quarantined';

    public function label(): string
    {
        return match ($this) {
            self::Threaded => 'Zugeordnet',
            self::Triage => 'Zu prüfen',
            self::Quarantined => 'Blockiert',
        };
    }
}
