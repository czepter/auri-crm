<?php

namespace App\Automation\Actions;

use App\Automation\Models\AutomationRule;
use App\Models\StudioSettings;

/**
 * E-09: loads config/automation.php's default rule set for the current
 * tenant. Matched on 'key', so a tenant's edit to a seeded rule survives a
 * second pass — firstOrCreate only inserts a key it has not seen.
 *
 * @return int how many were created
 */
class SeedDefaultAutomationRules
{
    public function __invoke(): int
    {
        $created = 0;

        foreach (config('automation.rules') as $entry) {
            // #439: a client-facing text is keyed formal/informal; the studio's register picks one.
            $entry = array_map(fn ($value) => is_array($value) ? $value[StudioSettings::current()->client_register] : $value, $entry);

            if (AutomationRule::query()->firstOrCreate(['key' => $entry['key']], $entry)->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
