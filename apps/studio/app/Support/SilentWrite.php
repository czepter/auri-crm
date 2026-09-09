<?php

namespace App\Support;

use Illuminate\Support\Facades\Context;

/**
 * Marks the current write as a backfill: something that already happened,
 * being entered now. The people in it lived through it months ago, so
 * everything the app would normally announce about it has no addressee.
 *
 * Kept in Context rather than on the Request because the announcements are
 * not all made while the request is open — Context is what survives into a
 * queued job, and NotificationService defers mail into one.
 */
class SilentWrite
{
    private const KEY = 'silent_write';

    public static function mark(): void
    {
        Context::add(self::KEY, true);
    }

    public static function active(): bool
    {
        return Context::get(self::KEY) === true;
    }
}
