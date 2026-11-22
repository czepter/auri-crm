<?php

namespace App\Finance\Actions;

use App\Finance\Models\FixedAsset;
use App\Models\User;
use App\Support\AuditLogger;

class ClearDisposal
{
    public function __invoke(FixedAsset $asset, ?User $actor = null): FixedAsset
    {
        $asset->update(['disposed_at' => null, 'disposal_proceeds' => null]);

        AuditLogger::record('finance.asset.disposal_cleared', $actor, [], $asset->public_id);

        return $asset;
    }
}
