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
enum LeadStage: string implements HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case QuoteSent = 'quote_sent';
    case QuoteAccepted = 'quote_accepted';
    case Booked = 'booked';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Contacted => 'Kontaktiert',
            self::QuoteSent => 'Angebot',
            self::QuoteAccepted => 'Angebot angenommen',
            self::Booked => 'Gebucht',
        };
    }

    /** Index in the funnel. The declaration order is the funnel order. */
    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public function next(): ?self
    {
        return self::cases()[$this->position() + 1] ?? null;
    }

    /**
     * Any other stage. Forward skipping is legitimate — a client accepts on the
     * phone before any formal quote existed, and forcing staff through the
     * intermediate stages would make the funnel a fiction rather than a record.
     *
     * Backwards is legitimate for the same reason. It was refused until a
     * mis-drop on the board had no undo, and refusing it did not keep the
     * funnel honest: it left the wrong stage standing, which is the worse
     * record. The direction is not lost — audit_logs holds from and to on
     * every move, so a correction reads as one.
     *
     * Only the no-op is refused, because it writes a stage_entered_at reset
     * and an audit row that say nothing happened.
     */
    public function canTransitionTo(self $next): bool
    {
        return $next !== $this;
    }
}
