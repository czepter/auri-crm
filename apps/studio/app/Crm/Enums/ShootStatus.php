<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The stages a fresh studio starts with, and nothing more.
 *
 * The funnel itself lives in the `stages` table now — renamed,
 * reordered and added to by the studio — and Lead::$stage reads a Stage row,
 * not a case of this enum. What is left here is the seed the migration writes
 * and the vocabulary factories and seeders name it by. Behaviour hangs off
 * StageAnchor; nothing may branch on a case below.
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
