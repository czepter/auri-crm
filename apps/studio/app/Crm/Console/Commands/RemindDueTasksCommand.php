<?php

namespace App\Crm\Console\Commands;

use App\Crm\Actions\RemindDueTasks;
use App\Portal\Actions\RemindClientTasks;
use Illuminate\Console\Command;

class RemindDueTasksCommand extends Command
{
    protected $signature = 'tasks:remind-due';

    protected $description = 'Reports tasks that are due or overdue to whoever owes them.';

    public function handle(RemindDueTasks $remind, RemindClientTasks $remindClients): int
    {
        $remindClients();
        $this->info(($remind)().' Empfänger benachrichtigt.');

        return self::SUCCESS;
    }
}
