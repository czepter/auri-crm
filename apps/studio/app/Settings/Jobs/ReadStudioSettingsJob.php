<?php

namespace App\Settings\Jobs;

use App\Models\StudioSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Proves T-01's acceptance criterion end to end: a queued job reads
 * StudioSettings::current() the way the real worker does for every job it
 * pops, and sees
 * whatever the web process most recently wrote, with no deploy and no
 * cache-clear command in between. No business job needs a studio setting
 * yet; this is the fixture App\Jobs\PingJob's own docblock already names as
 * the shape a future one takes.
 */
class ReadStudioSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'settings-runtime:observed-display-name';

    public function handle(): void
    {
        Cache::store('redis')->put(self::CACHE_KEY, StudioSettings::current()->display_name);
    }
}
