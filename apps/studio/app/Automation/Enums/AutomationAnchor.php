<?php

namespace App\Automation\Enums;

/**
 * The hard part E-08 exists for. PerShoot: "seven days before the shoot" is
 * a different date per shoot on a multi-shoot project — the sweep checks
 * every shoot's own date and fires against that Shoot. PrimaryShoot: "after
 * the wedding" means the one shoot flagged is_primary, and fires against its
 * Project rather than the Shoot itself — see AutomationRun's own docblock.
 *
 * Only set on a date-offset rule; null on an event-triggered one, whose
 * anchor is whichever Project or Shoot the firing event itself carries.
 */
enum AutomationAnchor: string
{
    case PerShoot = 'per_shoot';
    case PrimaryShoot = 'primary_shoot';
}
