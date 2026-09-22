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
 * gallery in the studio. That is the price of a gallery outside the bookings.
 */
class GalleryPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ManageShoots->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageShoots->value);
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
            ? $user->can(Capability::ManageShoots->value)
            : $this->projects->manageShoots($user, $gallery->project);
    }

    /**
     * ManagePortalAccess statt ManageShoots: die Faehigkeit entscheidet
     * laut ihrer eigenen Beschreibung bereits, ob eines der Projekte eines
     * Kontakts zeigbereit ist — eine Galerie freizugeben ist dieselbe Frage.
     * Wie manageMedia routet das ueber ProjectPolicy, sobald ein Projekt
     * dahintersteht: eine Galerie darf nicht freigebbar sein, wenn ihr
     * Projekt fuer diese Person unsichtbar ist.
     */
    public function publish(User $user, Gallery $gallery): bool
    {
        return $gallery->project === null
            ? $user->can(Capability::ManagePortalAccess->value)
            : $this->projects->manageVisibility($user, $gallery->project);
    }
}
