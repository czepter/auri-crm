<?php

namespace App\Policies;

use App\Crm\Models\Shoot;
use App\Models\User;

/**
 * Everything here routes through ClientCasePolicy, so a shoot can never be
 * reachable when the project holding it is not.
 */
class ShootPolicy
{
    public function __construct(private readonly ClientCasePolicy $projects) {}

    public function view(User $user, Shoot $shoot): bool
    {
        return $this->projects->view($user, $shoot->case);
    }

    public function update(User $user, Shoot $shoot): bool
    {
        return $this->projects->manageShoots($user, $shoot->case);
    }

    public function assignCrew(User $user, Shoot $shoot): bool
    {
        return $this->projects->manageCrew($user, $shoot->case);
    }

    /**
     * Add, check off and remove shot list items (E-05). Reuses ManageShoots
     * rather than a new capability: a shot list is shoot planning, exactly as
     * sensitive as the shoot it lives on.
     */
    public function manageShotList(User $user, Shoot $shoot): bool
    {
        return $this->projects->manageShoots($user, $shoot->case);
    }
}
