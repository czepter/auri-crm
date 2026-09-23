<?php

namespace App\Policies;

use App\Crm\Models\Task;
use App\Enums\Capability;
use App\Models\User;

class TaskPolicy
{
    public function __construct(private readonly ClientCasePolicy $projects) {}

    public function view(User $user, Task $task): bool
    {
        return $task->case === null || $this->projects->view($user, $task->case);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->case === null
            ? $user->can(Capability::ManageTasks->value)
            : $this->projects->manageTasks($user, $task->case);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageTasks->value);
    }
}
