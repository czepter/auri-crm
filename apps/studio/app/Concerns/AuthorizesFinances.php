<?php

namespace App\Concerns;

use App\Enums\Capability;

/**
 * 404 rather than 403, the AuthorizesStaffSettings convention: whoever lacks
 * ManageFinances has no reason to learn that /finanzen exists.
 */
trait AuthorizesFinances
{
    private function authorizeFinances(): void
    {
        abort_unless(
            auth('staff')->user()?->can(Capability::ManageFinances->value) ?? false,
            404,
        );
    }
}
