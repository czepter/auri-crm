<?php

namespace App\Policies;

use App\Gallery\Models\Gallery;
use App\Models\User;

/**
 * Everything here routes through ProjectPolicy, the same shape ShootPolicy
 * already uses for Shoot: a gallery can never be reachable when the project
 * holding it is not.
 */
class GalleryPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    /**
     * Reach the ingest screen, and start, resume, upload into, complete or
     * abort a multipart upload (G-02). Reuses ManageShoots rather than a new
     * capability — media ingest is shoot logistics, the same reasoning
     * ShootPolicy::manageShotList already applies to a shoot's checklist.
     */
    public function manageMedia(User $user, Gallery $gallery): bool
    {
        return $this->projects->manageShoots($user, $gallery->project);
    }
}
