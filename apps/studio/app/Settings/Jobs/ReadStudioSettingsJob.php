<?php

namespace App\Settings\Jobs;

use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Proves T-01's acceptance criterion end to end: a queued job resolves its
 * own StudioSettingsResolver from the container — a fresh instance, the same
 * as the real worker container gets for every job it pops — and reads
 * whatever the web process most recently wrote, with no deploy and no
 * cache-clear command in between. No business job needs a studio setting
 * yet; this is the fixture App\Jobs\PingJob's own docblock already names as
 * the shape a future one takes.
 */
class ReadStudioSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'settings-runtime:observed-display-name';

    public function handle(StudioSettingsResolver $settings): void
    {
        Cache::store('redis')->put(self::CACHE_KEY, $settings->displayName());
    }
}
