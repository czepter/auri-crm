<?php

namespace App\Policies;

use App\Crm\Models\Shoot;
use App\Models\User;

/**
 * Everything here routes through ProjectPolicy, so a shoot can never be
 * reachable when the project holding it is not.
 */
class ShootPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function view(User $user, Shoot $shoot): bool
    {
        return $this->projects->view($user, $shoot->project);
    }

    public function update(User $user, Shoot $shoot): bool
    {
        return $this->projects->manageShoots($user, $shoot->project);
    }

    public function assignCrew(User $user, Shoot $shoot): bool
    {
        return $this->projects->manageCrew($user, $shoot->project);
    }
}
