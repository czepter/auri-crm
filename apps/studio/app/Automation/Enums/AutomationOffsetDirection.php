<?php

namespace App\Automation\Enums;

/** Which side of the anchor date offset_days counts from. */
enum AutomationOffsetDirection: string
{
    case Before = 'before';
    case After = 'after';
}
