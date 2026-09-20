<?php

namespace App\Policies;

use App\Crm\Models\Task;
use App\Enums\Capability;
use App\Models\User;

class TaskPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function view(User $user, Task $task): bool
    {
        return $task->project === null || $this->projects->view($user, $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->project === null
            ? $user->role->allows(Capability::ManageTasks)
            : $this->projects->manageTasks($user, $task->project);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageTasks);
    }
}
