<?php

namespace App\Crm\Events;

use App\Crm\Models\Shoot;
use Illuminate\Foundation\Events\Dispatchable;

class ShootCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly Shoot $shoot,
        public readonly string $reason,
    ) {}
}
