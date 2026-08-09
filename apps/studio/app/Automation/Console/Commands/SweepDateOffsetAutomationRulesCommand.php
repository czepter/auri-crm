<?php

namespace App\Automation\Console\Commands;

use App\Automation\Actions\SweepDateOffsetAutomationRules;
use Illuminate\Console\Command;

/**
 * The scheduled entry point for App\Automation\Actions\
 * SweepDateOffsetAutomationRules — see bootstrap/app.php's withSchedule()
 * for the daily run.
 */
class SweepDateOffsetAutomationRulesCommand extends Command
{
    protected $signature = 'automation:sweep-date-offset-rules';

    protected $description = 'Fires every active date-offset AutomationRule whose anchor crosses today.';

    public function handle(SweepDateOffsetAutomationRules $sweep): int
    {
        $count = $sweep();

        $this->info("Fired {$count} automation rule(s).");

        return self::SUCCESS;
    }
}
