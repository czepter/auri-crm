<?php

use App\Releases\Support\ReleaseLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('reports an update when release-log knows a higher version', function () {
    config(['app.version' => '0.9.1']);
    Cache::forever('release-log.latest', '0.9.2');

    expect(app(ReleaseLog::class)->updateAvailable())->toBeTrue();
});

it('reports nothing on the newest version', function () {
    config(['app.version' => '0.9.2']);
    Cache::forever('release-log.latest', '0.9.2');

    expect(app(ReleaseLog::class)->updateAvailable())->toBeFalse();
});

// version_compare sorts 'dev' below every number, so without the guard in
// ReleaseLog every branch build would announce itself as out of date.
it('reports nothing for a build that carries no version number', function () {
    config(['app.version' => 'dev']);
    Cache::forever('release-log.latest', '0.9.2');

    expect(app(ReleaseLog::class)->updateAvailable())->toBeFalse();
});

it('reports nothing before the first fetch', function () {
    config(['app.version' => '0.9.1']);

    expect(app(ReleaseLog::class)->updateAvailable())->toBeFalse();
});

it('does nothing without a configured service', function () {
    config(['services.release_log.url' => null]);
    Http::fake();

    expect(app(ReleaseLog::class)->refresh())->toBe(-1);

    Http::assertNothingSent();
});

it('stores the feed, the latest version and every release behind it', function () {
    config(['services.release_log.url' => 'https://release-log.test/']);

    Http::preventStrayRequests();
    Http::fake([
        'release-log.test/versions' => Http::response([
            'latest' => '0.9.2',
            'versions' => [['version' => '0.9.2'], ['version' => '0.9.1']],
        ]),
        'release-log.test/releases?*' => Http::response([
            'releases' => [
                ['version' => '0.9.2', 'sections' => [['key' => 'new', 'count' => 1]]],
                ['version' => '0.9.1', 'sections' => [['key' => 'new', 'count' => 22]]],
            ],
        ]),
        'release-log.test/releases/*' => Http::response(['version' => '0.9.2', 'changes' => []]),
    ]);

    $log = app(ReleaseLog::class);

    expect($log->refresh())->toBe(2)
        ->and($log->latest())->toBe('0.9.2')
        ->and($log->feed())->toHaveCount(2)
        ->and($log->release('0.9.2'))->not->toBeNull()
        ->and($log->release('0.8.0'))->toBeNull();
});

// Ein Nachlauf, der scheitert, darf den Screen nicht leeren: gespeichert wird
// ohne Ablauf, aufgefrischt wird vom Kommando.
it('keeps what it stored when a later fetch fails', function () {
    config(['services.release_log.url' => 'https://release-log.test']);

    Http::preventStrayRequests();
    Http::fake([
        'release-log.test/versions' => Http::sequence()
            ->push(['latest' => '0.9.2', 'versions' => []])
            ->push(null, 500),
        'release-log.test/releases?*' => Http::response(['releases' => [['version' => '0.9.2']]]),
        'release-log.test/releases/*' => Http::response(['version' => '0.9.2', 'changes' => []]),
    ]);

    $log = app(ReleaseLog::class);
    $log->refresh();

    expect(fn () => $log->refresh())->toThrow(Exception::class)
        ->and($log->feed())->toHaveCount(1)
        ->and($log->latest())->toBe('0.9.2');
});
