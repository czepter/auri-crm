<?php

namespace App\Crm\Console\Commands;

use App\Crm\Actions\RemindStalledLeads;
use Illuminate\Console\Command;

class RemindStalledLeadsCommand extends Command
{
    protected $signature = 'leads:remind-stalled';

    protected $description = 'Reports inquiries that have not moved to the studio.';

    public function handle(RemindStalledLeads $remind): int
    {
        $this->info(($remind)().' Empfänger benachrichtigt.');

        return self::SUCCESS;
    }
}
