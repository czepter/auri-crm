<?php

namespace App\Concerns;

use App\Enums\Capability;

/**
 * The 404-not-403 gate StudioSettingsController (T-01) established for every
 * settings screen: nobody without ManageSettings has a reason to learn a
 * studio's settings live behind a given path at all, so a denied role gets
 * the same response as a route that does not exist rather than a 403 that
 * confirms one does. Shared here once T-04's NumberRangeController needed
 * the identical check a second time.
 */
trait AuthorizesStaffSettings
{
    private function authorizeSettings(): void
    {
        abort_unless(
            auth('staff')->user()?->can(Capability::ManageSettings->value) ?? false,
            404,
        );
    }
}
