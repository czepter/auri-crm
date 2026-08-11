<?php

namespace App\Integration\Jobs;

use App\Integration\Enums\WebhookDeliveryStatus;
use App\Integration\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * I-04: one HTTP POST attempt at one WebhookDelivery, signed with its
 * endpoint's secret. Retries live in the delivery row itself
 * (attempts/next_retry_at), not in Laravel's own $tries/backoff() — a
 * WebhookDelivery already has to survive a hand replay
 * (`webhook:redeliver`) independent of any queue mechanics, so the schedule
 * lives where both paths can read and update it the same way.
 *
 * BACKOFF_SECONDS[n] is the delay before the (n+2)th attempt — 1 min, 5 min,
 * 30 min, 2 hr, capped at four retries after the first try (five attempts
 * total) before the delivery is marked Failed for good.
 */
class DeliverWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var list<int> */
    private const BACKOFF_SECONDS = [60, 300, 1800, 7200];

    private const MAX_ATTEMPTS = 5;

    public function __construct(public readonly WebhookDelivery $delivery) {}

    public function handle(): void
    {
        $endpoint = $this->delivery->endpoint;

        // An endpoint can be switched off between dispatch and delivery — no
        // attempt is made against it, and the row is left exactly where it
        // was rather than counted as a failure.
        if (! $endpoint->active) {
            return;
        }

        $body = $this->delivery->body();
        $signature = $endpoint->sign($body);

        $this->delivery->forceFill([
            'signature' => $signature,
            'attempts' => $this->delivery->attempts + 1,
        ])->save();

        try {
            $response = Http::withHeaders(['X-Signature' => $signature])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException) {
            $this->retryOrFail(null, null);

            return;
        }

        if ($response->successful()) {
            $this->delivery->forceFill([
                'status' => WebhookDeliveryStatus::Delivered,
                'response_status' => $response->status(),
                'response_body' => $response->body(),
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $this->retryOrFail($response->status(), $response->body());
    }

    private function retryOrFail(?int $status, ?string $body): void
    {
        $attempt = $this->delivery->attempts;
        $delay = self::BACKOFF_SECONDS[$attempt - 1] ?? null;

        if ($attempt >= self::MAX_ATTEMPTS || $delay === null) {
            $this->delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'response_status' => $status,
                'response_body' => $body,
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $nextRetryAt = now()->addSeconds($delay);

        $this->delivery->forceFill([
            'status' => WebhookDeliveryStatus::Pending,
            'response_status' => $status,
            'response_body' => $body,
            'next_retry_at' => $nextRetryAt,
        ])->save();

        self::dispatch($this->delivery)->delay($nextRetryAt);
    }
}
