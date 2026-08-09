<?php

namespace App\Automation\Enums;

/**
 * What starts an AutomationRule. Event: a domain event class name in
 * event_class fires it the instant that event dispatches. DateOffset: a
 * daily sweep (App\Automation\Actions\SweepDateOffsetAutomationRules) fires
 * it the day a shoot or project crosses anchor/offset_days/direction.
 *
 * Exactly one of the two column groups is populated per row — see
 * AutomationRule's own docblock for which.
 */
enum AutomationTriggerType: string
{
    case Event = 'event';
    case DateOffset = 'date_offset';
}
