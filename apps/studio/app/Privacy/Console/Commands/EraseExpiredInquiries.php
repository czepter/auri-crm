<?php

namespace App\Privacy\Console\Commands;

use App\Models\StudioSettings;
use App\Privacy\Actions\ApplyRetention;
use Illuminate\Console\Command;

/** #501: der tägliche Lauf der Löschfrist; was er tut, bestimmt `retention_policy`. */
class EraseExpiredInquiries extends Command
{
    protected $signature = 'retention:erase';

    protected $description = 'Anonymisiert oder löscht abgelaufene verlorene und abgelehnte Anfragen, wenn das Studio eine Frist gesetzt hat.';

    public function handle(ApplyRetention $apply): int
    {
        $months = StudioSettings::current()->retention_months;
        $count = $months === null ? 0 : $apply($months);

        $this->info("{$count} bearbeitet.");

        return self::SUCCESS;
    }
}
