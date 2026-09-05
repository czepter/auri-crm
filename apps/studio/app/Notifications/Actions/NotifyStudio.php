<?php

namespace App\Notifications\Actions;

use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\Studio\StudioEventNotification;
use App\Notifications\Support\StudioAudience;

/**
 * The one way a staff-facing event reaches people. Domain code raises a
 * StudioEventNotification and hands it here; who receives it is
 * StudioAudience's question and whether they receive it is
 * NotificationService's, so neither is answered at a call site.
 *
 * App\Gallery\Actions\NotifyStudioOfEngagement is the same fan-out one story
 * earlier, still fanning to every user because a gallery names no crew.
 */
class NotifyStudio
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioAudience $audience,
    ) {}

    public function __invoke(StudioEventNotification $notification): void
    {
        $this->audience
            ->forProject($notification->project())
            ->each(fn (User $user) => $this->notifications->send($user, $notification));
    }
}
