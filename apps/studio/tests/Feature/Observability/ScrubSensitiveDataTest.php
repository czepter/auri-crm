<?php

use App\Support\Observability\ScrubSensitiveData;
use Sentry\Event;
use Sentry\UserDataBag;

// F-11's acceptance criterion: a payload carrying a password, a recovery
// code or a signed URL is scrubbed before send, proven against the real
// `before_send` hook (config/sentry.php) rather than by inspection alone.
it('strips password, TOTP and recovery-code fields from the request body', function () {
    $event = Event::createEvent();
    $event->setRequest([
        'url' => 'https://studio.test/login',
        'data' => [
            'password' => 'hunter2',
            'password_confirmation' => 'hunter2',
            'totp_code' => '123456',
            'recovery_code' => 'ABCD-1234',
            'name' => 'Lena Meier',
        ],
    ]);

    $scrubbed = (new ScrubSensitiveData)($event);

    $data = $scrubbed->getRequest()['data'];

    expect($data['password'])->toBe('[Filtered]')
        ->and($data['password_confirmation'])->toBe('[Filtered]')
        ->and($data['totp_code'])->toBe('[Filtered]')
        ->and($data['recovery_code'])->toBe('[Filtered]')
        ->and($data['name'])->toBe('Lena Meier');
});

it('strips the signature and expiry off a signed URL, in both the url and query string', function () {
    $event = Event::createEvent();
    $event->setRequest([
        'url' => 'https://studio.test/gallery/download?expires=1999999999&signature=abcdef0123456789',
        'query_string' => 'expires=1999999999&signature=abcdef0123456789',
    ]);

    $scrubbed = (new ScrubSensitiveData)($event);
    $request = $scrubbed->getRequest();

    expect($request['url'])->not->toContain('abcdef0123456789')
        ->and($request['query_string'])->not->toContain('abcdef0123456789');
});

it('redacts the Authorization and Cookie headers', function () {
    $event = Event::createEvent();
    $event->setRequest([
        'headers' => [
            'Authorization' => 'Bearer secret-token',
            'Cookie' => 'studio_admin_session=abc123',
            'Accept' => 'application/json',
        ],
    ]);

    $scrubbed = (new ScrubSensitiveData)($event);
    $headers = $scrubbed->getRequest()['headers'];

    expect($headers['Authorization'])->toBe('[Filtered]')
        ->and($headers['Cookie'])->toBe('[Filtered]')
        ->and($headers['Accept'])->toBe('application/json');
});

it('redacts a client email address wherever it appears in the request body', function () {
    $event = Event::createEvent();
    $event->setRequest([
        'data' => [
            'email' => 'lena@example.test',
            'notes' => 'not an address',
        ],
    ]);

    $scrubbed = (new ScrubSensitiveData)($event);

    expect($scrubbed->getRequest()['data']['email'])->toBe('[Filtered]')
        ->and($scrubbed->getRequest()['data']['notes'])->toBe('not an address');
});

it('redacts the user email set on the event', function () {
    $event = Event::createEvent();
    $event->setUser((new UserDataBag)->setEmail('lena@example.test'));

    $scrubbed = (new ScrubSensitiveData)($event);

    expect($scrubbed->getUser()->getEmail())->toBe('[Filtered]');
});
