<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The forward-only production pipeline (vision §7). Kept separate from
 * LeadStage so neither becomes a fourteen-state enum in which half the
 * transitions are illegal.
 *
 * Cancellation is deliberately not a case here. It is reachable from any
 * status, and a tenth value would erase which position the shoot died in. See
 * Shoot::$cancelled_at.
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

    /** Index in the pipeline. The declaration order is the pipeline order. */
    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public function next(): ?self
    {
        return self::cases()[$this->position() + 1] ?? null;
    }

    /**
     * Forward only, but skipping is allowed: prep and culling are checklists a
     * photographer may never tick, and forcing a click through them records a
     * lie rather than a fact. Backwards is rejected — a shoot that returns to
     * booked after delivery makes every downstream count untrustworthy.
     */
    public function canTransitionTo(self $next): bool
    {
        return $next->position() > $this->position();
    }
}
