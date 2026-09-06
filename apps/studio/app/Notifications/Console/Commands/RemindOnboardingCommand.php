<?php

namespace App\Notifications\Console\Commands;

use App\Notifications\Actions\RemindOnboarding;
use Illuminate\Console\Command;

class RemindOnboardingCommand extends Command
{
    protected $signature = 'notifications:remind-onboarding';

    protected $description = 'Sends the setup-pending reminder to a studio whose owner has not finished onboarding within two days.';

    public function handle(RemindOnboarding $remind): int
    {
        $count = $remind();

        $this->info($count > 0 ? 'Sent the onboarding reminder.' : 'Nothing to remind.');

        return self::SUCCESS;
    }
}
