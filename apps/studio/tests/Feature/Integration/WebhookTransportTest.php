<?php

use App\Integration\Enums\WebhookDeliveryStatus;
use App\Integration\Jobs\DeliverWebhookDelivery;
use App\Integration\Models\WebhookDelivery;
use App\Integration\Models\WebhookEndpoint;
use App\Models\User;
use App\Notifications\DomainEventOccurred;
use App\Notifications\NotificationService;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// I-04: WebhookTransport is registered on NotificationService's own
// registry (App\Providers\AppServiceProvider) — every test here goes
// through app(NotificationService::class)->send(...), never the transport
// directly, so a regression that turns this back into a second fan-out
// would show up here first.

it('dispatches a queued delivery per active endpoint subscribed to the notification type', function () {
    Queue::fake();
    $endpoint = WebhookEndpoint::factory()->subscribedTo('ProjectBooked')->create();
    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new DomainEventOccurred('ProjectBooked', ['project' => 'proj_abc']));

    $delivery = WebhookDelivery::sole();
    expect($delivery->webhook_endpoint_id)->toBe($endpoint->id)
        ->and($delivery->event_type)->toBe('ProjectBooked')
        ->and($delivery->payload)->toBe(['project' => 'proj_abc'])
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->tenant_id)->not->toBeNull()
        ->and($delivery->public_id)->not->toBeNull();

    Queue::assertPushed(DeliverWebhookDelivery::class, fn (DeliverWebhookDelivery $job) => $job->delivery->is($delivery));
});

it('never notifies an endpoint not subscribed to the event type', function () {
    Queue::fake();
    WebhookEndpoint::factory()->subscribedTo('ContractSigned')->create();
    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new DomainEventOccurred('ProjectBooked', ['project' => 'proj_abc']));

    expect(WebhookDelivery::count())->toBe(0);
    Queue::assertNotPushed(DeliverWebhookDelivery::class);
});

it('never notifies an inactive endpoint even when it is subscribed', function () {
    Queue::fake();
    WebhookEndpoint::factory()->inactive()->subscribedTo('ProjectBooked')->create();
    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new DomainEventOccurred('ProjectBooked', ['project' => 'proj_abc']));

    expect(WebhookDelivery::count())->toBe(0);
    Queue::assertNotPushed(DeliverWebhookDelivery::class);
});

it('registers as a transport on E-07: firing the real InstallmentPaid domain event reaches a subscribed endpoint', function () {
    // No direct Event::listen wired straight to WebhookTransport anywhere —
    // App\Integration\Listeners\DispatchWebhookNotifications is the only
    // thing standing between this event and NotificationService::send().
    Queue::fake();
    $endpoint = WebhookEndpoint::factory()->subscribedTo('InstallmentPaid')->create();
    $installment = PaymentInstallment::factory()->create();

    event(new InstallmentPaid($installment));

    $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->sole();
    expect($delivery->event_type)->toBe('InstallmentPaid')
        ->and($delivery->payload)->toBe(['installment' => $installment->public_id]);
});
