<?php

use App\Jobs\PingJob;
use Illuminate\Support\Facades\Queue;

// G-01a: media.ingest and media.zip get their own supervisors and timeouts,
// the same way M-00's QueueRuntimeTest proves it for default/mail/render —
// a 20s thumbnail job and a 40min ZIP build must not share a worker pool.
it('names media.ingest and media.zip queues, each with its own redis supervisor and timeout', function () {
    $supervisors = config('horizon.defaults');

    expect($supervisors)->toHaveKeys(['supervisor-media-ingest', 'supervisor-media-zip'])
        ->and($supervisors['supervisor-media-ingest']['queue'])->toBe(['media.ingest'])
        ->and($supervisors['supervisor-media-zip']['queue'])->toBe(['media.zip']);

    foreach (['supervisor-media-ingest', 'supervisor-media-zip'] as $name) {
        expect($supervisors[$name]['connection'])->toBe('redis')
            ->and($supervisors[$name]['timeout'])->toBeInt()->toBeGreaterThan(0);
    }

    expect($supervisors['supervisor-media-ingest']['timeout'])
        ->not->toBe($supervisors['supervisor-media-zip']['timeout']);

    // 40 minutes, per the story: a ZIP build is the longest-running workload
    // this application has.
    expect($supervisors['supervisor-media-zip']['timeout'])->toBe(2400);
});

it('pushes jobs onto media.ingest and media.zip independently of every other queue', function () {
    Queue::fake();

    PingJob::dispatch()->onQueue('media.ingest');
    PingJob::dispatch()->onQueue('media.zip');

    Queue::assertPushedOn('media.ingest', PingJob::class);
    Queue::assertPushedOn('media.zip', PingJob::class);
});
