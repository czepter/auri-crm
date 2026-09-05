<?php

namespace App\Crm\Events;

use App\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new inquiry entered the system. P4's automation engine hangs the
 * inquiry auto-reply off this; nothing listens yet.
 */
class LeadCaptured
{
    use Dispatchable;

    public function __construct(public readonly Lead $lead) {}
}
