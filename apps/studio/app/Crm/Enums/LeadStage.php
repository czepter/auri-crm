<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The sales funnel (vision §7). quote_accepted is its own stage because "they
 * said yes, send the contract" is the one funnel moment where the studio is
 * the bottleneck, and folding it into quote_sent hides it.
 *
 * Lost is deliberately not a case here. It is reachable from any stage, and a
 * seventh value would erase which stage the lead died in — the number the
 * funnel is actually measured on. See Lead::$lost_at.
 */
#[TypeScript]
enum LeadStage: string implements HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case QuoteSent = 'quote_sent';
    case QuoteAccepted = 'quote_accepted';
    case ContractSent = 'contract_sent';
    case Booked = 'booked';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Contacted => 'Kontaktiert',
            self::QuoteSent => 'Angebot',
            self::QuoteAccepted => 'Angebot angenommen',
            self::ContractSent => 'Vertrag',
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
