<?php

namespace App\Notifications\Transports;

use App\Integration\Enums\WebhookDeliveryStatus;
use App\Integration\Jobs\DeliverWebhookDelivery;
use App\Integration\Models\WebhookDelivery;
use App\Integration\Models\WebhookEndpoint;
use App\Notifications\Contracts\Transport;
use App\Notifications\Contracts\WebhookSubscribable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * I-04: registers as a transport on E-07 rather than a second fan-out — see
 * NotificationService's own docblock and issue #86's acceptance criterion.
 * Every WebhookEndpoint in the current tenant (App\Concerns\
 * BelongsToTenant's global scope, same as every other tenant-scoped query)
 * subscribed to this notification's event type gets one WebhookDelivery row
 * and one queued App\Integration\Jobs\DeliverWebhookDelivery — never an
 * inline HTTP call from here, so a slow or dead receiver can never make
 * NotificationService::send() itself slow.
 */
class WebhookTransport implements Transport
{
    public function channel(): string
    {
        return 'webhook';
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        $eventType = $notification instanceof WebhookSubscribable
            ? $notification->webhookEventType()
            : Str::afterLast($notification::class, '\\');

        $payload = $notification->toArray($notifiable);

        WebhookEndpoint::query()
            ->where('active', true)
            ->get()
            ->each(function (WebhookEndpoint $endpoint) use ($eventType, $payload) {
                if (! $endpoint->subscribesTo($eventType)) {
                    return;
                }

                $delivery = WebhookDelivery::create([
                    'webhook_endpoint_id' => $endpoint->id,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'status' => WebhookDeliveryStatus::Pending,
                ]);

                DeliverWebhookDelivery::dispatch($delivery);
            });
    }
}
