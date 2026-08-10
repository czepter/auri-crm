<?php

namespace App\Notifications;

use App\Notifications\Contracts\WebhookSubscribable;
use Illuminate\Notifications\Notification;

/**
 * I-04: the envelope a domain event is wrapped in on its way through
 * NotificationService — see App\Integration\Listeners\
 * DispatchWebhookNotifications, which is the only thing that constructs one.
 * A plain Laravel event (App\Crm\Events\ProjectBooked and friends) has no
 * via() of its own to ask, so this is what stands in for it at the one
 * fan-out choke point rather than WebhookTransport subscribing to those
 * events directly — see NotificationService's own docblock on why that
 * distinction matters.
 *
 * webhook-only on purpose: nothing in this codebase renders these six
 * events as mail or an in-app row today, so via() names only the channel
 * that actually has a subscriber.
 */
class DomainEventOccurred extends Notification implements WebhookSubscribable
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $eventType,
        private readonly array $payload,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['webhook'];
    }

    public function webhookEventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
