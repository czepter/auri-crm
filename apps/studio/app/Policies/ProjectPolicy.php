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
        if ($user->can(Capability::ViewAllProjects->value)) {
            return true;
        }

        return $project->isCrewedBy($user);
    }

    /** A closed pursuit is not bookable again until it is reopened. */
    public function book(User $user, Project $project): bool
    {
        return $user->can(Capability::BookProject->value)
            && ! ($project->lead?->isLost() ?? false);
    }

    /** The capability that captures an inquiry may take it back, as on the API. */
    public function delete(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageLeads->value) && $this->view($user, $project);
    }

    public function manageShoots(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageShoots->value) && $this->view($user, $project);
    }

    public function manageCrew(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageCrew->value) && $this->view($user, $project);
    }

    public function manageTasks(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageTasks->value) && $this->view($user, $project);
    }

    public function manageNotes(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageNotes->value) && $this->view($user, $project);
    }

    /** Reply on this project's Thread (E-06) — never originate one; see StartThread. */
    public function manageMessages(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageMessages->value) && $this->view($user, $project);
    }

    /** Flip portal_visible for this one project (A-04) — never a contact. */
    public function manageVisibility(User $user, Project $project): bool
    {
        return $user->can(Capability::ManagePortalAccess->value) && $this->view($user, $project);
    }

    public function manageQuotes(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageQuotes->value) && $this->view($user, $project);
    }

    /**
     * Seed and personalise this project's questionnaires (E-02). Reuses
     * Capability::ManageFormTemplates rather than a new capability:
     * personalising a project's copy is the same domain of work as authoring
     * the template it may have started from, done by the same roles.
     */
    public function manageForms(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageFormTemplates->value) && $this->view($user, $project);
    }

    /** Draft, render and send a contract for this project. Never required — nothing waits for one. */
    public function manageContracts(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageContracts->value) && $this->view($user, $project);
    }

    /**
     * Create or edit this project's payment plan (M-03). Reuses
     * Capability::ManageContracts rather than a new capability, so it stays
     * with the same roles rather than opening to every role ManageQuotes
     * already trusts.
     */
    public function managePaymentPlan(User $user, Project $project): bool
    {
        return $user->can(Capability::ManageContracts->value) && $this->view($user, $project);
    }
}
