<?php

namespace App\Releases\Console\Commands;

use App\Releases\Support\ReleaseLog;
use Illuminate\Console\Command;

/**
 * The scheduled entry point for App\Releases\Support\ReleaseLog — see
 * routes/console.php. Daily: releases are cut by hand, and the sidebar dot
 * appearing a few hours after a tag is what this screen is worth.
 */
class FetchReleasesCommand extends Command
{
    protected $signature = 'releases:fetch';

    protected $description = 'Reads the release list from release-log into the cache the version screens serve.';

    public function handle(ReleaseLog $log): int
    {
        $count = $log->refresh();

        if ($count === -1) {
            $this->comment('No RELEASE_LOG_URL configured, nothing fetched.');

            return self::SUCCESS;
        }

        $this->info("Stored {$count} release(s).");

        return self::SUCCESS;
    }
}
