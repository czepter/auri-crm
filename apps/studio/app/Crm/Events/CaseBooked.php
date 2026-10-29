<?php

namespace App\Crm\Events;

use App\Crm\Models\ClientCase;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The anchor several later features hang off: S-03's task templates apply
 * here. Fires exactly once per project,
 * because BookCase only books a project that is not booked yet.
 */
class CaseBooked
{
    use Dispatchable;

    public function __construct(public readonly ClientCase $project) {}
}
