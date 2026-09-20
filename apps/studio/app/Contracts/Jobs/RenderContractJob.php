<?php

namespace App\Contracts\Jobs;

use App\Contracts\Actions\EnsureContractArtifact;
use App\Contracts\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs on the `render` queue (config/horizon.php's supervisor-render, M-00),
 * which is why the caller only ever dispatches this and returns — never waits
 * on it.
 *
 * The render itself lives in EnsureContractArtifact, because the contract
 * screens reach for it too: a contract with no bytes is built by whoever
 * needs it first, the worker or the reader. Its own guards cover what this
 * job used to check for itself — bytes already there, or a signature that
 * must not have new ones minted under it.
 */
class RenderContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Contract $contract) {}

    public function handle(EnsureContractArtifact $ensure): void
    {
        $contract = $this->contract->fresh();

        // Deleted since dispatch.
        if ($contract === null) {
            return;
        }

        $ensure($contract);
    }
}
