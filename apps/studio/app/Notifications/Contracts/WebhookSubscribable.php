<?php

namespace App\Notifications\Contracts;

/**
 * I-04: the name a WebhookEndpoint's `subscribed_events` array matches
 * against. A Notification lists 'webhook' in its own via() to reach
 * App\Notifications\Transports\WebhookTransport at all (the same rule every
 * other channel already follows); implementing this interface is what then
 * tells that transport which endpoints want it specifically. A notification
 * that skips this interface still delivers, keyed on class_basename(...) —
 * see the transport's own docblock — so this is an opt-in refinement, not a
 * requirement.
 */
interface WebhookSubscribable
{
    public function webhookEventType(): string;
}
