<?php

namespace App\Notifications;

use App\Notifications\Contracts\Transactional;
use App\Notifications\Contracts\Transport;
use App\Notifications\Support\NotificationPreferences;
use App\Support\AuditLogger;
use App\Support\SilentWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * E-07: the single fan-out choke point. Every notification in the system —
 * mail and in-app — leaves through here,
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
 * T-11: this is also the only place preferences are ever consulted — never
 * a Transport, never a Notification class itself. A notification
 * implementing App\Notifications\Contracts\Transactional skips the check
 * entirely; every other notification is checked here, before a transport is
 * ever called, so "nobody got notified" always has exactly one place to
 * look.
 */
class NotificationService
{
    /** @param iterable<Transport> $transports */
    public function __construct(
        private readonly iterable $transports,
        private readonly NotificationPreferences $preferences,
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
