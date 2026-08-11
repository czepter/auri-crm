<?php

use App\Integration\Enums\WebhookDeliveryStatus;
use App\Integration\Jobs\DeliverWebhookDelivery;
use App\Integration\Models\WebhookDelivery;
use App\Integration\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('creates a WebhookEndpoint with a generated secret and the given subscribed events', function () {
    $this->artisan('webhook-endpoint:create', [
        'url' => 'https://example.test/hook',
        '--event' => ['ProjectBooked', 'ContractSigned'],
    ])->assertSuccessful();

    $endpoint = WebhookEndpoint::sole();
    expect($endpoint->url)->toBe('https://example.test/hook')
        ->and($endpoint->subscribed_events)->toBe(['ProjectBooked', 'ContractSigned'])
        ->and($endpoint->active)->toBeTrue()
        ->and($endpoint->secret)->not->toBeEmpty()
        ->and($endpoint->public_id)->not->toBeNull();
});

it("replays a WebhookDelivery's exact stored payload as a new delivery attempt, leaving the original untouched", function () {
    Queue::fake();
    $endpoint = WebhookEndpoint::factory()->create();
    $original = WebhookDelivery::factory()->for($endpoint, 'endpoint')->failed()->create([
        'event_type' => 'ProjectBooked',
        'payload' => ['project' => 'proj_abc'],
    ]);

    $this->artisan('webhook:redeliver', ['delivery' => $original->public_id])->assertSuccessful();

    expect(WebhookDelivery::count())->toBe(2);

    $replay = WebhookDelivery::where('id', '!=', $original->id)->sole();
    expect($replay->webhook_endpoint_id)->toBe($original->webhook_endpoint_id)
        ->and($replay->event_type)->toBe('ProjectBooked')
        ->and($replay->payload)->toBe(['project' => 'proj_abc'])
        ->and($replay->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($replay->attempts)->toBe(0)
        ->and($replay->public_id)->not->toBe($original->public_id);

    // The replayed original is left exactly as it was decided.
    expect($original->fresh()->status)->toBe(WebhookDeliveryStatus::Failed);

    Queue::assertPushed(DeliverWebhookDelivery::class, fn (DeliverWebhookDelivery $job) => $job->delivery->is($replay));
});

it('fails when redelivering an unknown public_id', function () {
    $this->artisan('webhook:redeliver', ['delivery' => 'does-not-exist'])->assertFailed();
});
