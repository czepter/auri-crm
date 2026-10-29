<?php

namespace App\Notifications\Contracts;

/**
 * A notification describing a state that is still true in the morning, so a
 * staff member who never touched the pane gets it in the briefing rather
 * than at once. NotificationMode::defaultFor() reads this; the kernel cannot
 * name the domain's notifications, so each one says it for itself.
 */
interface BriefedByDefault {}
