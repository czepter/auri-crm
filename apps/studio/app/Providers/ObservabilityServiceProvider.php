<?php

namespace App\Providers;

use App\Support\Observability\JobRequestId;
use App\Support\Observability\TagSurface;
use App\Support\RequestId;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

use function Sentry\configureScope;

/**
 * F-11: everything that carries a request id or a surface tag from an HTTP
 * request onto a queued job, and from there into GlitchTip. See
 * App\Http\Middleware\AssignRequestId for the edge half (minting, echoing,
 * general log context) and App\Support\Observability\JobRequestId for the
 * worker-side half this wires up below.
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(JobRequestId $jobRequestId): void
    {
        // Fixed for the whole process (APP_SURFACE is set once, at boot, per
        // container — App\Support\Surface) — configuring the scope once here
        // is enough for it to carry onto every event this process ever
        // reports, request or queue job alike.
        configureScope(new TagSurface);

        // The dispatch-side half: every job pushed onto any queue connection
        // — from an HTTP request or otherwise — gets the current request id
        // stamped into its payload. `array_filter` drops the key entirely
        // when there isn't one (an Artisan command, the scheduler), so a job
        // dispatched outside a request never carries a stale value.
        Queue::createPayloadUsing(fn (?string $connection, ?string $queue, ?array $payload) => array_filter([
            'requestId' => RequestId::current(),
        ]));

        Queue::before(fn (JobProcessing $event) => $jobRequestId->forProcessing($event));
        Queue::after(fn (JobProcessed $event) => $jobRequestId->clear($event));
        Queue::failing(fn (JobFailed $event) => $jobRequestId->forFailure($event));
    }
}
