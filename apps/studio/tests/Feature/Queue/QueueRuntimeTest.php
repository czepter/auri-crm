<?php

use App\Jobs\FailingJob;
use App\Jobs\PingJob;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('forwards REDIS_HOST to the processes `artisan serve` spawns', function () {
    // Found while smoke-testing this story: `artisan serve` only forwards a
    // whitelist of variables to its spawned worker (Illuminate\Foundation\
    // Console\ServeCommand::$passthroughVariables), and REDIS_HOST isn't on
    // it by default — dev-server.sh's serve process would fall back to
    // .env's own 127.0.0.1 and never reach the redis Compose service, so a
    // job dispatched from the browser would silently fail to queue. Same gap
    // AppServiceProvider already closed for APP_SURFACE; see that comment.
    expect(ServeCommand::$passthroughVariables)->toContain('REDIS_HOST');
});

it('names a queue per workload, each with a redis supervisor and its own timeout', function () {
    // M-01 (render) and later stories (mail) dispatch onto these; nothing does
    // yet, which is why this reads the config rather than a real dispatch.
    $supervisors = config('horizon.defaults');

    expect($supervisors)->toHaveKeys(['supervisor-default', 'supervisor-mail', 'supervisor-render'])
        ->and($supervisors['supervisor-default']['queue'])->toBe(['default'])
        ->and($supervisors['supervisor-mail']['queue'])->toBe(['mail'])
        ->and($supervisors['supervisor-render']['queue'])->toBe(['render']);

    foreach ($supervisors as $supervisor) {
        expect($supervisor['connection'])->toBe('redis')
            ->and($supervisor['timeout'])->toBeInt()->toBeGreaterThan(0);
    }

    // Every queue's timeout is distinct on purpose — render legitimately runs
    // longer than a notification, and one shared number would either starve
    // render jobs or let a stuck mail call sit for minutes.
    $timeouts = collect($supervisors)->pluck('timeout');
    expect($timeouts->unique())->toHaveCount($timeouts->count());
});

it('pushes a dispatched job onto the queue named for its workload', function () {
    Queue::fake();

    PingJob::dispatch()->onQueue('render');

    Queue::assertPushedOn('render', PingJob::class);
});

it('runs a dispatched job, per the sync-in-testing connection phpunit.xml already forces', function () {
    // QUEUE_CONNECTION=sync in phpunit.xml (see the comment there): a plain
    // dispatch() already executes inline, with no worker process to fake.
    expect(config('queue.default'))->toBe('sync');

    Cache::forget(PingJob::CACHE_KEY);

    PingJob::dispatch();

    expect(Cache::get(PingJob::CACHE_KEY))->not->toBeNull();
});

it('sends a job that fails every attempt to the failed_jobs table, and lets it be retried', function () {
    // A real Illuminate\Queue\Worker popping a real queued job, rather than a
    // fake or an inline sync call — the sync connection never reaches the
    // failed-job path at all, because nothing catches the exception it
    // throws. This is the "boots real infra" tier for the queue: the
    // database connection stands in for redis so the assertion holds without
    // a live Horizon/Redis in the test process (neither of the studio CI job
    // nor this Pest run start one), while exercising the same
    // Illuminate\Queue\Worker + failed_jobs pipeline Horizon's redis-backed
    // workers run in Compose and in the deployed worker.
    FailingJob::dispatch()->onConnection('database')->onQueue('queue-runtime-test');

    expect(DB::table('queue_jobs')->count())->toBe(1);

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--queue' => 'queue-runtime-test',
        '--once' => true,
        '--tries' => 1,
    ]);

    $failed = DB::table('failed_jobs')->sole();

    expect($failed->exception)->toContain('FailingJob fails on purpose')
        ->and(DB::table('queue_jobs')->count())->toBe(0);

    // The documented retry path (docs/queue.md): `queue:retry`, which is what
    // Horizon's own Retry button calls for a redis-backed failed job.
    Artisan::call('queue:retry', ['id' => [$failed->uuid]]);

    expect(DB::table('queue_jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});
