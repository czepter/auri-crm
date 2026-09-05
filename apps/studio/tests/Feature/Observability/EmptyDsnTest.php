<?php

use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\Transport\ResultStatus;

// F-11's acceptance-critical bit: with an empty DSN, nothing is transmitted
// — not "the config defaults to empty", but that the actual, app-configured
// transport never reaches an HTTP client. Sentry\Transport\HttpTransport::
// send() returns a "skipped" result and returns before it ever touches its
// http client when the DSN is null, which is what this asserts against the
// real transport instance the container resolves — not a fake standing in
// for it, and no request is made either way.
it('never transmits when SENTRY_LARAVEL_DSN is empty, the default for CI and local dev', function () {
    expect(config('sentry.dsn'))->toBeEmpty();

    $client = app(HubInterface::class)->getClient();

    expect($client)->not->toBeNull()
        ->and($client->getOptions()->getDsn())->toBeNull();

    $result = $client->getTransport()->send(Event::createEvent());

    expect((string) $result->getStatus())->toBe((string) ResultStatus::skipped());
});

it('lets an unhandled exception report through the real handler without throwing, DSN empty', function () {
    // The path a real 500 takes: Handler::report() -> Sentry\Laravel\
    // Integration's reportable callback -> Client::captureException(). If
    // wiring this up ever made a report() call throw (a misconfigured
    // client, a callback that assumes a DSN), this would catch it — DSN
    // empty is exactly the state CI and local dev run in.
    expect(fn () => report(new RuntimeException('F-11 empty-DSN smoke test — never actually sent')))
        ->not->toThrow(Throwable::class);
});
