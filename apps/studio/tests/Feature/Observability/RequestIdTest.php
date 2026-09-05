<?php

use App\Jobs\FailingJob;
use App\Support\Observability\JobRequestId;
use App\Support\RequestId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class)
    // RequestId::$current is a plain static (see its own docblock for why),
    // so it survives across test methods in this same process unless a test
    // explicitly resets it — this file's tests that dispatch a job outside
    // of any request need a clean slate rather than whatever the previous
    // test left behind.
    ->beforeEach(fn () => RequestId::set(null));

it('echoes back the X-Request-Id the caller sent', function () {
    Route::get('/__test/f-11/echo', fn () => response()->noContent());

    $this->withHeaders(['X-Request-Id' => 'caller-supplied-id'])
        ->get('/__test/f-11/echo')
        ->assertNoContent()
        ->assertHeader('X-Request-Id', 'caller-supplied-id');
});

it('mints an X-Request-Id when the caller sent none, and echoes that one back', function () {
    Route::get('/__test/f-11/echo-minted', fn () => response()->noContent());

    $response = $this->get('/__test/f-11/echo-minted')->assertNoContent();

    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

it('stamps the dispatching request\'s id onto a job pushed during that request', function () {
    Route::get('/__test/f-11/dispatch-probe', function () {
        FailingJob::dispatch()->onConnection('database')->onQueue('f-11-probe');

        return response()->noContent();
    });

    $this->withHeaders(['X-Request-Id' => 'dispatching-request-id'])
        ->get('/__test/f-11/dispatch-probe');

    $payload = json_decode(DB::table('queue_jobs')->sole()->payload, true);

    expect($payload['requestId'])->toBe('dispatching-request-id');
});

it('carries no requestId when a job is dispatched outside a request', function () {
    RequestId::set(null);

    FailingJob::dispatch()->onConnection('database')->onQueue('f-11-probe');

    $payload = json_decode(DB::table('queue_jobs')->sole()->payload, true);

    expect($payload)->not->toHaveKey('requestId');
});

it('reports the X-Request-Id of the request that dispatched it, on a real queue failure', function () {
    // Same "boots real infra" tier as QueueRuntimeTest's own failed-job
    // test: a real Illuminate\Queue\Worker popping a real queued job off the
    // database connection, not a sync-driver inline call (which re-throws
    // rather than running the failure-reporting pipeline at all) and not a
    // fake.
    Route::get('/__test/f-11/dispatch-failing', function () {
        FailingJob::dispatch()->onConnection('database')->onQueue('f-11-failure-test');

        return response()->noContent();
    });

    $this->withHeaders(['X-Request-Id' => 'req-that-dispatched-it'])
        ->get('/__test/f-11/dispatch-failing');

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--queue' => 'f-11-failure-test',
        '--once' => true,
        '--tries' => 1,
    ]);

    $failed = DB::table('failed_jobs')->sole();
    $payload = json_decode($failed->payload, true);

    // This is what the failure-reporting pipeline (App\Support\
    // Observability\JobRequestId::forFailure, wired in
    // App\Providers\ObservabilityServiceProvider) reads back off on the
    // worker side to attach to the failure report.
    expect($payload['requestId'])->toBe('req-that-dispatched-it');
});

it('recovers the dispatching request\'s id from a failed job\'s own payload', function () {
    // The worker-side half in isolation, per the acceptance criterion's
    // preference for testing this against a constructed event rather than a
    // live queue round-trip — RequestIdTest's own test just above already
    // covers the round-trip.
    RequestId::set(null);

    $payload = json_encode([
        'requestId' => 'req-recovered-from-payload',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => ['commandName' => FailingJob::class],
        'uuid' => 'test-uuid',
    ]);

    $job = new SyncJob(app(), $payload, 'sync', 'default');
    $event = new JobFailed('sync', $job, new RuntimeException('boom'));

    (new JobRequestId)->forFailure($event);

    expect(RequestId::current())->toBe('req-recovered-from-payload');
});
