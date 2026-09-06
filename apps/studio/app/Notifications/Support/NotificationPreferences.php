<?php

namespace App\Notifications\Support;

use App\Notifications\Enums\NotificationMode;
use App\Notifications\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * T-11: whether a given (notifiable, notification, channel) triple is turned
 * off. Read exclusively from inside NotificationService::send(), never from
 * a Transport — see that service's own docblock for why this is the single
 * place preferences are ever consulted.
 *
 * An on-demand address (Notification::route(...), the shape StaffInvited and
 * ClientAccessLink both send through) carries no identity a preference row
 * could key on, so it is never suppressed — the same reasoning
 * InAppTransport already applies to require a persisted Eloquent model.
 */
class NotificationPreferences
{
    public function suppresses(mixed $notifiable, Notification $notification, string $channel): bool
    {
        if (! $notifiable instanceof Model || ! $notifiable->exists) {
            return false;
        }

        return ! $this->enabled($notifiable, $notification::class, $channel);
    }

    /**
     * The same lookup by class name rather than by instance — what a
     * preferences screen renders its checkboxes from, where no notification
     * has been constructed and none should have to be.
     */
    public function enabled(Model $notifiable, string $notificationClass, string $channel): bool
    {
        $row = $this->row($notifiable, $notificationClass, $channel);

        if ($row === null) {
            return NotificationMode::defaultFor($notificationClass)->allows($channel);
        }

        // T-23: the mode is the answer where there is one. `enabled` still
        // answers for the '*' rows a client unsubscribe writes — those name
        // no event and so have no mode.
        return $row->mode?->allows($channel) ?? $row->enabled;
    }

    /**
     * T-23: which of the four ways this person reads this event. The stored
     * value where the pane has been used, the event's own default otherwise
     * — a fresh staff member has no rows and must still land on briefing for
     * the two scans that default there.
     */
    public function mode(Model $notifiable, string $notificationClass): NotificationMode
    {
        return $this->row($notifiable, $notificationClass, 'mail')?->mode
            ?? NotificationMode::defaultFor($notificationClass);
    }

    /**
     * The one writer of the four-way choice: both channel rows carry the same
     * mode, so neither can be read on its own and disagree with the other.
     */
    public function setMode(Model $notifiable, string $notificationClass, NotificationMode $mode): void
    {
        foreach (['mail', 'in_app'] as $channel) {
            NotificationPreference::query()->updateOrCreate(
                [
                    'notifiable_type' => $notifiable->getMorphClass(),
                    'notifiable_id' => $notifiable->getKey(),
                    'notification_class' => $notificationClass,
                    'channel' => $channel,
                ],
                ['enabled' => $mode->allows($channel), 'mode' => $mode],
            );
        }
    }

    private function row(Model $notifiable, string $notificationClass, string $channel): ?NotificationPreference
    {
        return NotificationPreference::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->where('channel', $channel)
            ->whereIn('notification_class', [$notificationClass, '*'])
            ->get()
            // An exact class match says more than the wildcard: a staff
            // member who turned off "Angebot versendet" specifically, but
            // never clicked unsubscribe, still gets everything else.
            ->sortBy(fn (NotificationPreference $row) => $row->notification_class === '*' ? 1 : 0)
            ->first();
    }

    /**
     * Writes one channel row — the client one-click unsubscribe ('*' as
     * $notificationClass) and anything else that speaks in channels, never
     * NotificationPreference::create() directly. The staff pane goes through
     * setMode() above instead.
     *
     * Clears the mode: a row that says "mail off" and "immediate" at once
     * would answer differently depending on which of the two was read.
     */
    public function set(Model $notifiable, string $notificationClass, string $channel, bool $enabled): void
    {
        NotificationPreference::query()->updateOrCreate(
            [
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'notification_class' => $notificationClass,
                'channel' => $channel,
            ],
            ['enabled' => $enabled, 'mode' => null],
        );
    }

    /**
     * True once a notifiable holds a disabled '*' row for the given channel
     * — the client-facing "you are unsubscribed" state a portal screen would
     * show, and what the unsubscribe confirmation reads before writing.
     */
    public function unsubscribed(Model $notifiable, string $channel = 'mail'): bool
    {
        return NotificationPreference::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->where('notification_class', '*')
            ->where('channel', $channel)
            ->where('enabled', false)
            ->exists();
    }
}
