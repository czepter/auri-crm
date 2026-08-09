<?php

namespace App\Quotes\Events;

use App\Quotes\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A quote was revised: $revision is the new row, $predecessor is the one it
 * just superseded, both already committed. Dispatched last inside
 * App\Quotes\Actions\ReviseQuote's own transaction — the same place
 * App\Crm\Actions\CaptureInquiry dispatches LeadCaptured — so it is also the
 * cheapest place to prove that transaction is real: a listener that throws
 * has to leave neither the revision nor the supersede behind.
 */
class QuoteRevised
{
    use Dispatchable;

    public function __construct(
        public readonly Quote $predecessor,
        public readonly Quote $revision,
    ) {}
}
