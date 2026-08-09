<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Proves the queue runtime end to end: dispatch this, and its cache key
 * appears once the worker container (or, in tests, the sync-forced testing
 * connection — see phpunit.xml) has actually run it. M-00 ships no business
 * job of its own; this is the fixture that stands in for one until M-01
 * dispatches real work.
 */
class PingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'queue-runtime:last-ping';

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, now()->toIso8601String());
    }
}
