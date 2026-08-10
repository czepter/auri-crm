<?php

namespace App\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Media\Actions\RetryFailedAsset;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use Illuminate\Http\RedirectResponse;

/**
 * G-04: the "needs attention" tray's one action — re-running ingest for a
 * Failed asset. The tray itself is a section of the ingest screen
 * (MediaIngestController::show), not a screen of its own.
 */
class FailedAssetController extends Controller
{
    public function retry(Asset $asset, RetryFailedAsset $retry): RedirectResponse
    {
        $this->authorize('manageMedia', $asset->gallery);

        abort_unless($asset->status === AssetStatus::Failed, 409);

        $retry($asset);

        return back();
    }
}
