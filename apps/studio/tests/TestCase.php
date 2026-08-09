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
    }
}
