<?php

namespace App\Crm\Events;

use App\Crm\Models\Job;
use App\Crm\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The anchor several later features hang off: S-03's task templates apply
 * here. Fires exactly once per project,
 * because BookProject refuses to create a second Job.
 */
class ProjectBooked
{
    use Dispatchable;

    public function __construct(
        public readonly Project $project,
        public readonly Job $job,
    ) {}
}
