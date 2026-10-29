<?php

namespace App\Staff\Console\Commands;

use App\Staff\Actions\SendStudioDigests;
use Illuminate\Console\Command;

/**
 * The scheduled entry point for App\Staff\Actions\SendStudioDigests
 * — see routes/console.php for the daily schedule that runs this. Daily even
 * for a monthly rhythm: the action decides who is due.
 */
class SendStudioDigestsCommand extends Command
{
    protected $signature = 'notifications:send-digests';

    protected $description = 'Mails every staff member whose summary is due the events they chose to read in one.';

    public function handle(SendStudioDigests $send): int
    {
        $count = $send();

        $this->info("Sent {$count} digest(s).");

        return self::SUCCESS;
    }
}
