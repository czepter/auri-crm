<?php

namespace App\Integration\Enums;

/**
 * A delivery's whole lifecycle (I-04): Pending until a 2xx response is seen
 * or the backoff schedule (App\Integration\Jobs\DeliverWebhookDelivery)
 * exhausts its attempts, then Delivered or Failed — terminal either way,
 * except that `webhook:redeliver` always starts a fresh row at Pending
 * rather than resetting one already decided.
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
