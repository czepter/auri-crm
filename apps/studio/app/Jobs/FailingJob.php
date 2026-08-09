<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Fails on purpose, every time, so the runtime's failure path — the
 * `failed_jobs` table and the retry from Horizon — has something real to
 * exercise. See docs/queue.md for the retry path this proves.
 */
class FailingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('FailingJob fails on purpose — see app/Jobs/FailingJob.php.');
    }
}
