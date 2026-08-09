<?php

namespace App\Automation\Console\Commands;

use App\Automation\Models\AutomationRule;
use Illuminate\Console\Command;

/**
 * E-09: loads config/automation.php's default rule set for the current
 * tenant (see config/tenancy.php — one studio today; App\Concerns\
 * BelongsToTenant scopes the lookup and the create automatically).
 *
 * Matched on 'key', so re-running this after a tenant has edited a seeded
 * rule's content leaves that edit alone — firstOrCreate only ever inserts
 * the row the first time its key is seen.
 */
class SeedDefaultAutomationRulesCommand extends Command
{
    protected $signature = 'automation:seed-default-rules';

    protected $description = 'Creates the default AutomationRule set from config/automation.php, skipping any key already seeded.';

    public function handle(): int
    {
        $created = 0;

        foreach (config('automation.rules') as $entry) {
            $rule = AutomationRule::query()->firstOrCreate(['key' => $entry['key']], $entry);

            if ($rule->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Seeded {$created} new automation rule(s).");

        return self::SUCCESS;
    }
}
