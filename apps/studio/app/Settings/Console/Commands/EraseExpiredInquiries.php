<?php

namespace App\Settings\Console\Commands;

use App\Settings\Actions\ApplyRetention;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Console\Command;

/** #501: der tägliche Lauf der Löschfrist; was er tut, bestimmt `retention_policy`. */
class EraseExpiredInquiries extends Command
{
    protected $signature = 'retention:erase';

    protected $description = 'Anonymisiert oder löscht abgelaufene verlorene und abgelehnte Anfragen, wenn das Studio eine Frist gesetzt hat.';

    public function handle(StudioSettingsResolver $settings, ApplyRetention $apply): int
    {
        $months = $settings->retentionMonths();
        $count = $months === null ? 0 : $apply($months);

        $this->info("{$count} bearbeitet.");

        return self::SUCCESS;
    }
}
