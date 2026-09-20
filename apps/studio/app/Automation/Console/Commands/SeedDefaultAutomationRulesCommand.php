<?php

namespace App\Automation\Console\Commands;

use App\Automation\Actions\SeedDefaultAutomationRules;
use Illuminate\Console\Command;

class SeedDefaultAutomationRulesCommand extends Command
{
    protected $signature = 'automation:seed-default-rules';

    protected $description = 'Creates the default AutomationRule set from config/automation.php, skipping any key already seeded.';

    public function handle(SeedDefaultAutomationRules $seed): int
    {
        $this->info("Seeded {$seed()} new automation rule(s).");

        return self::SUCCESS;
    }
}
