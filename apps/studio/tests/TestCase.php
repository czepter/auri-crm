<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Inertia root view calls @vite, which reads public/build/manifest.json
        // — build output, so gitignored and absent from every fresh checkout. Ten
        // page tests would 500 on ViteManifestNotFoundException before asserting
        // anything, and the studio CI job never builds the frontend.
        //
        // Faking the manifest rather than building it keeps the PHP suite
        // independent of the asset pipeline. Whether the real assets resolve is a
        // question for the browser, and the e2e job builds for real and answers it.
        $this->withoutVite();

        // StudioSettingsResolver reads and busts its cache through the `redis`
        // store by name, which walks straight past CACHE_STORE=array — so one
        // Redis is shared by every test process at once. `artisan test
        // --parallel` runs six, and tests/Pest.php flushes that store before
        // each test in Feature: worker A's flush lands between worker B's
        // cache warm-up and the query count it takes right after, and B fails
        // on a number it never caused. Pointing the store at the array driver
        // gives each test its own, which is what the per-test flush was
        // reaching for anyway.
        config(['cache.stores.redis' => ['driver' => 'array', 'serialize' => false]]);
    }
}
