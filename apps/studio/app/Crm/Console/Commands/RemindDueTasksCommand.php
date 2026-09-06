<?php

namespace App\Crm\Console\Commands;

use App\Crm\Actions\RemindDueTasks;
use Illuminate\Console\Command;

class RemindDueTasksCommand extends Command
{
    protected $signature = 'tasks:remind-due';

    protected $description = 'Reports tasks that are due or overdue to whoever owes them.';

    public function handle(RemindDueTasks $remind): int
    {
        $this->info(($remind)().' Empfänger benachrichtigt.');

        return self::SUCCESS;
    }
}
