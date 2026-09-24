<?php

namespace App\Gallery\Actions;

use App\Models\User;
use App\Notifications\NotificationService;
use Illuminate\Notifications\Notification;

/**
 * G-12: fans one gallery-engagement notification (a favorite or a comment)
 * out to every staff user of the tenant. Gallery carries no assigned
 * photographer or crew list of its own to narrow this to — case_id is
 * nullable (a gallery frequently spans the whole project rather than one
 * shoot, Gallery's own doc comment) — and issue #71's acceptance criterion
 * says "the studio", not one person. User::query() is already tenant-scoped
 * by BelongsToTenant's global scope, the same as everything else this
 * request touches.
 */
class NotifyStudioOfEngagement
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function __invoke(Notification $notification): void
    {
        User::query()->each(fn (User $user) => $this->notifications->send($user, $notification));
    }
}
