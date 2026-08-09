<?php

namespace App\Crm\Events;

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Shoot;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One event carrying both ends rather than nine classes. A listener acting on
 * "left culling without ever being edited" cannot reconstruct the origin from
 * the shoot afterwards.
 */
class ShootStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Shoot $shoot,
        public readonly ShootStatus $from,
        public readonly ShootStatus $to,
    ) {}
}
