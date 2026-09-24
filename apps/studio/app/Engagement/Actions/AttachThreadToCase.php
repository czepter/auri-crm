<?php

namespace App\Engagement\Actions;

use App\Crm\Models\ClientCase;
use App\Engagement\Models\Thread;
use LogicException;

/**
 * P-04 (#223): the studio's side of a thread that started without a booking.
 * "Do you still have anything free in autumn?" becomes a wedding, and the
 * conversation that led there belongs on it.
 *
 * One direction only. A thread that already names a project is not moved to
 * a different one and not detached — the messages on it were written about
 * that order, and re-pointing them would rewrite what was said rather than
 * file it.
 */
class AttachThreadToCase
{
    public function __invoke(Thread $thread, ClientCase $project): Thread
    {
        if ($thread->case_id !== null) {
            throw new LogicException('This thread already belongs to a booking; a conversation is filed once, never moved.');
        }

        $thread->update(['case_id' => $project->id]);

        return $thread;
    }
}
