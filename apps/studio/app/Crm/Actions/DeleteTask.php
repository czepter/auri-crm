<?php

namespace App\Crm\Actions;

use App\Crm\Models\Task;

class DeleteTask
{
    public function __invoke(Task $task): void
    {
        $task->delete();
    }
}
