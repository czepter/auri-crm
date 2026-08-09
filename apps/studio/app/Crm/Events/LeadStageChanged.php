<?php

namespace App\Crm\Events;

use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Carries both stages rather than only the new one: a listener that wants to
 * act on "left quote_sent without accepting" cannot reconstruct the origin
 * from the lead afterwards.
 */
class LeadStageChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly LeadStage $from,
        public readonly LeadStage $to,
    ) {}
}
