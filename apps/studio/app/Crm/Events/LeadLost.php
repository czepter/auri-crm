<?php

namespace App\Crm\Events;

use App\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

class LeadLost
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $reason,
    ) {}
}
