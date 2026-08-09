<?php

namespace App\Policies;

use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Models\User;

/**
 * Who may see a project, and who may commit the studio to one.
 *
 * The two questions are deliberately separate. Seeing a project is ordinary
 * studio work; booking it is the one action that turns a pursuit into an
 * obligation, and S-06 exists because P-05a shipped it open to everyone.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        // A crew role has no project-wide list, but does have their own
        // shoots, so the screens that list projects scope rather than refuse.
        return true;
    }

    /**
     * Either the role sees everything, or this person is crewed on one of the
     * project's shoots. Nothing in between — there is no "shared with me".
     */
    public function view(User $user, Project $project): bool
    {
        if ($user->role->allows(Capability::ViewAllProjects)) {
            return true;
        }

        return $project->isCrewedBy($user);
    }

    public function book(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::BookProject);
    }

    public function manageShoots(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManageShoots) && $this->view($user, $project);
    }

    public function manageCrew(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManageCrew) && $this->view($user, $project);
    }

    public function manageTasks(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManageTasks) && $this->view($user, $project);
    }

    public function manageNotes(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManageNotes) && $this->view($user, $project);
    }

    /** Flip portal_visible for this one project (A-04) — never a contact. */
    public function manageVisibility(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManagePortalAccess) && $this->view($user, $project);
    }

    public function manageQuotes(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManageQuotes) && $this->view($user, $project);
    }

    /** Generate a rendered contract for this project (M-01). */
    public function manageContracts(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManageContracts) && $this->view($user, $project);
    }

    /**
     * Create or edit this project's payment plan (M-03). Reuses
     * Capability::ManageContracts rather than a new capability: a payment
     * plan is created against a signed contract and is exactly as sensitive
     * as the contract it depends on, so it stays with the same roles rather
     * than opening to every role ManageQuotes already trusts.
     */
    public function managePaymentPlan(User $user, Project $project): bool
    {
        return $user->role->allows(Capability::ManageContracts) && $this->view($user, $project);
    }
}
