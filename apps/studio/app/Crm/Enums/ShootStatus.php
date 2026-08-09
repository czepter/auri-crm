<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The forward-only production pipeline (vision §7). Kept separate from
 * LeadStage so neither becomes a fourteen-state enum in which half the
 * transitions are illegal.
 *
 * The full set exists now so the column never has to be widened, but sprint 2
 * only ever writes Requested — the transition machinery is P-03, in sprint 3.
 */
#[TypeScript]
enum ShootStatus: string implements HasLabel
{
    case Requested = 'requested';
    case Booked = 'booked';
    case Prep = 'prep';
    case Shot = 'shot';
    case Culling = 'culling';
    case Editing = 'editing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Angefragt',
            self::Booked => 'Gebucht',
            self::Prep => 'Vorbereitung',
            self::Shot => 'Fotografiert',
            self::Culling => 'Auswahl',
            self::Editing => 'Bearbeitung',
            self::Ready => 'Fertig',
            self::Delivered => 'Geliefert',
            self::Archived => 'Archiviert',
        };
    }
}
