<?php

namespace App\Staff\Console\Commands;

use App\Staff\Actions\BuildMorningBriefing;
use Illuminate\Console\Command;

class SendMorningBriefingCommand extends Command
{
    protected $signature = 'notifications:send-morning-briefing';

    protected $description = 'Sends each staff member one mail with what today holds.';

    public function handle(BuildMorningBriefing $build): int
    {
        $this->info(($build)().' Tagesüberblicke versendet.');

        return self::SUCCESS;
    }
}
