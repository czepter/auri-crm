<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Gallery\Models\Gallery;
use App\Models\User;

/**
 * Everything with a project routes through ProjectPolicy, the same shape
 * ShootPolicy already uses for Shoot: a gallery can never be reachable when
 * the project holding it is not.
 *
 * Without one there is nothing to route through, so the capability decides
 * alone — a role whose project view is narrowed still sees every projectless
 * gallery in the studio. That is the price of a gallery outside the bookings,
 * and the answer to disliking it is to stop making them, not to invent a
 * filter with nothing to filter on.
 */
class GalleryPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function viewAny(User $user): bool
    {
        return $user->role->allows(Capability::ManageShoots);
    }

    public function create(User $user): bool
    {
        return $user->role->allows(Capability::ManageShoots);
    }

    /**
     * Reach the ingest screen, and start, resume, upload into, complete or
     * abort a multipart upload (G-02). Reuses ManageShoots rather than a new
     * capability — media ingest is shoot logistics, the same reasoning
     * ShootPolicy::manageShotList already applies to a shoot's checklist.
     */
    public function manageMedia(User $user, Gallery $gallery): bool
    {
        return $gallery->project === null
            ? $user->role->allows(Capability::ManageShoots)
            : $this->projects->manageShoots($user, $gallery->project);
    }
}
