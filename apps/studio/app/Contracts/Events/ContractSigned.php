<?php

namespace App\Contracts\Events;

use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Carries the full Contract and ContractSignature, the same shape
 * App\Crm\Events\ProjectBooked carries Project and Job — so P-05b (sprint 9)
 * can act on a signature without re-querying it. Fires exactly once per
 * contract, because SignContract refuses to write a second signature row.
 */
class ContractSigned
{
    use Dispatchable;

    public function __construct(
        public readonly Contract $contract,
        public readonly ContractSignature $signature,
    ) {}
}
