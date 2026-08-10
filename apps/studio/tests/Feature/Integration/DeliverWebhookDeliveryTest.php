<?php

use App\Integration\Enums\WebhookDeliveryStatus;
use App\Integration\Jobs\DeliverWebhookDelivery;
use App\Integration\Models\WebhookDelivery;
use App\Integration\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('signs the exact request body with the endpoint secret and posts it as X-Signature', function () {
    Http::fake(['receiver.test/*' => Http::response('ok', 200)]);
    $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://receiver.test/hook', 'secret' => 'whsec_test_secret']);
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'event_type' => 'ProjectBooked',
        'payload' => ['project' => 'proj_abc'],
    ]);

    (new DeliverWebhookDelivery($delivery))->handle();

    $expectedSignature = hash_hmac('sha256', $delivery->body(), 'whsec_test_secret');

    Http::assertSent(function (HttpRequest $request) use ($expectedSignature) {
        return $request->url() === 'https://receiver.test/hook'
            && $request->header('X-Signature')[0] === $expectedSignature
            && hash_hmac('sha256', (string) $request->body(), 'whsec_test_secret') === $expectedSignature;
    });

    expect($delivery->fresh()->signature)->toBe($expectedSignature);
});

it('marks a delivery Delivered on a 2xx response', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    $delivery = WebhookDelivery::factory()->for(WebhookEndpoint::factory(), 'endpoint')->create();

    (new DeliverWebhookDelivery($delivery))->handle();

    $delivery->refresh();
    expect($delivery->status)->toBe(WebhookDeliveryStatus::Delivered)
        ->and($delivery->response_status)->toBe(200)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->next_retry_at)->toBeNull();
});

it('advances the attempt count and schedules the next retry on the documented backoff schedule', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('server error', 500)]);
    $delivery = WebhookDelivery::factory()->for(WebhookEndpoint::factory(), 'endpoint')->create();

    // 1 min, 5 min, 30 min, 2 hr — the schedule the job's own docblock names.
    $expectedDelays = [60, 300, 1800, 7200];

    foreach ($expectedDelays as $index => $seconds) {
        $attempt = $index + 1;
        (new DeliverWebhookDelivery($delivery->fresh()))->handle();

        $delivery->refresh();
        $actualDelay = $delivery->next_retry_at->getTimestamp() - now()->getTimestamp();

        expect($delivery->attempts)->toBe($attempt)
            ->and($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
            ->and($actualDelay)->toBeGreaterThan($seconds - 3)
            ->toBeLessThan($seconds + 3);
    }

    // The 5th and final attempt exhausts the schedule — no further retry.
    (new DeliverWebhookDelivery($delivery->fresh()))->handle();

    $delivery->refresh();
    expect($delivery->attempts)->toBe(5)
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::Failed)
        ->and($delivery->next_retry_at)->toBeNull();
});

it('marks a delivery Failed after exhausting every retry, without dispatching a 6th attempt', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('server error', 500)]);
    $delivery = WebhookDelivery::factory()->for(WebhookEndpoint::factory(), 'endpoint')->create();

    for ($i = 0; $i < 5; $i++) {
        (new DeliverWebhookDelivery($delivery->fresh()))->handle();
    }

    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Failed);

    Queue::assertPushedTimes(DeliverWebhookDelivery::class, 4);
});

it('treats a connection failure the same as a non-2xx response for backoff purposes', function () {
    Queue::fake();
    Http::fake(['*' => fn () => throw new ConnectionException('connection refused')]);
    $delivery = WebhookDelivery::factory()->for(WebhookEndpoint::factory(), 'endpoint')->create();

    (new DeliverWebhookDelivery($delivery))->handle();

    $delivery->refresh();
    expect($delivery->attempts)->toBe(1)
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->next_retry_at)->not->toBeNull();
});

it('skips a delivery whose endpoint was deactivated after it was queued', function () {
    Http::fake();
    $endpoint = WebhookEndpoint::factory()->create();
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();
    $endpoint->update(['active' => false]);

    (new DeliverWebhookDelivery($delivery->fresh()))->handle();

    Http::assertNothingSent();
    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->fresh()->attempts)->toBe(0);
});
