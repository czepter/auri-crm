<?php

namespace App\Support\Observability;

use App\Support\RequestId;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;

use function Sentry\configureScope;

/**
 * The worker-side half of F-11's request id propagation. App\Providers\
 * ObservabilityServiceProvider stamps `requestId` onto a job's payload as it
 * is pushed (Queue::createPayloadUsing); this reads it back off on the
 * worker so a failed job's report names the request that dispatched it,
 * exactly as the story's acceptance criterion asks.
 *
 * A tiny class of its own, not inline closures in the provider: it's what
 * makes "a failed job reports the X-Request-Id of the request that
 * dispatched it" testable directly against a constructed JobFailed event,
 * per the acceptance criterion's own preference for that over a live queue
 * round-trip. See tests/Feature/Observability/RequestIdTest.php.
 */
class JobRequestId
{
    public function forProcessing(JobProcessing $event): void
    {
        $this->apply($this->fromPayload($event->job->payload()));
    }

    public function forFailure(JobFailed $event): void
    {
        $this->apply($this->fromPayload($event->job->payload()));
    }

    public function clear(JobProcessed $event): void
    {
        RequestId::set(null);
    }

    private function fromPayload(array $payload): ?string
    {
        return $payload['requestId'] ?? null;
    }

    private function apply(?string $id): void
    {
        RequestId::set($id);

        if ($id === null) {
            return;
        }

        Log::withContext(['request_id' => $id]);
        configureScope(fn (Scope $scope) => $scope->setTag('request_id', $id));
    }
}
