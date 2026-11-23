<?php

namespace App\Finance\Actions;

use App\Finance\Models\FixedAsset;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class DisposeFixedAsset
{
    public function __invoke(FixedAsset $asset, string $date, int $proceeds, ?User $actor = null): FixedAsset
    {
        if ($asset->acquired_at->gt($date)) {
            throw ValidationException::withMessages(['disposed_at' => __('finance.beforeAcquisition')]);
        }

        $asset->update(['disposed_at' => $date, 'disposal_proceeds' => $proceeds]);

        AuditLogger::record('finance.asset.disposed', $actor, ['disposed_at' => $date, 'proceeds' => $proceeds], $asset->public_id);

        return $asset;
    }
}
