<?php

namespace App\Policies;

use App\Crm\Models\TaskTemplate;
use App\Enums\Capability;
use App\Models\User;

/**
 * Task templates are tenant-wide, like DocumentTemplate — no per-project
 * ownership to check, only the capability. T-14's own exception (design
 * §3 D4): ManageTasks, not ManageSettings, because editing what a booked
 * shoot owes is the same work as managing tasks, done in the settings room
 * rather than on a project.
 */
class TaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ManageTasks->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageTasks->value);
    }

    public function update(User $user, TaskTemplate $template): bool
    {
        return $user->can(Capability::ManageTasks->value);
    }

    public function delete(User $user, TaskTemplate $template): bool
    {
        return $user->can(Capability::ManageTasks->value);
    }
}
