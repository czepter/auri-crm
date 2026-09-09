<?php

namespace App\Notifications;

use App\Notifications\Contracts\Transactional;
use App\Notifications\Contracts\Transport;
use App\Notifications\Jobs\DeferredNotification;
use App\Notifications\Support\NotificationPreferences;
use App\Notifications\Support\QuietHours;
use App\Support\AuditLogger;
use App\Support\SilentWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * E-07: the single fan-out choke point. Every notification in the system —
 * mail, in-app, and later a webhook transport (I-04) — leaves through here,
 * never through Illuminate\Support\Facades\Notification or a model's own
 * ->notify() called straight from domain code. Domain code names the
 * notifiable and the Notification subclass describing what happened; that
 * subclass's own via() is the only place a channel is picked — the same
 * question ->notify() would ask of it directly — but funnelled through one
 * registry of App\Notifications\Contracts\Transport implementations rather
 * than scattered across every call site.
 *
 * Registered transports live in App\Providers\AppServiceProvider. Adding a
 * channel means adding a Transport to that array, never a new
 * Event::listen() wired straight to a domain event — see Transport's own
 * docblock and issue #68's acceptance criterion.
 *
 * T-11: this is also the only place preferences and quiet hours are ever
 * consulted — never a Transport, never a Notification class itself. A
 * notification implementing App\Notifications\Contracts\Transactional skips
 * both checks entirely; every other notification is checked here, before a
 * transport is ever called, so "nobody got notified" always has exactly one
 * place to look.
 */
class NotificationService
{
    /** @param iterable<Transport> $transports */
    public function __construct(
        private readonly iterable $transports,
        private readonly NotificationPreferences $preferences,
        private readonly QuietHours $quietHours,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        // Ahead of the Transactional exemption, which every other check here
        // sits behind: a backfilled contract's ContractSigned mail is exactly
        // as transactional as a live one and exactly as unwanted, because the
        // contract was signed last spring.
        if (SilentWrite::active()) {
            AuditLogger::record(
                event: 'notification.silenced',
                context: ['notification' => $notification::class],
                subject: $notifiable instanceof Model ? ($notifiable->public_id ?? null) : null,
            );

            return;
        }

        $channels = $notification->via($notifiable);
        $transactional = $notification instanceof Transactional;

        // Held whole, not channel by channel: a mail arriving at 03:00 is
        // the support call this story exists to prevent, and splitting the
        // in-app row out to arrive early while the mail waits would announce
        // the same event twice. Re-dispatched for later delivery rather than
        // dropped — see DeferredNotification's own docblock.
        if (! $transactional && in_array('mail', $channels, true) && $this->quietHours->isQuiet()) {
            DeferredNotification::dispatch($notifiable, $notification)->delay($this->quietHours->opensAt());

            return;
        }

        foreach ($this->transports as $transport) {
            $channel = $transport->channel();

            if (! in_array($channel, $channels, true)) {
                continue;
            }

            if (! $transactional && $this->preferences->suppresses($notifiable, $notification, $channel)) {
                $this->auditSuppression($notifiable, $notification, $channel);

                continue;
            }

            $transport->send($notifiable, $notification);
        }
    }

    private function auditSuppression(mixed $notifiable, Notification $notification, string $channel): void
    {
        AuditLogger::record(
            event: 'notification.suppressed',
            context: [
                'notification' => $notification::class,
                'channel' => $channel,
                'notifiable_type' => $notifiable instanceof Model ? $notifiable->getMorphClass() : null,
            ],
            subject: $notifiable instanceof Model ? ($notifiable->public_id ?? null) : null,
        );
    }
}
