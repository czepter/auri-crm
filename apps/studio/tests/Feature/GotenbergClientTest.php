<?php

use App\Support\GotenbergClient;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('posts the html to the chromium html-to-pdf route and returns the response body', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-bytes', 200)]);

    $body = (new GotenbergClient('http://gotenberg-test:3000'))->htmlToPdf('<p>Hallo</p>');

    expect($body)->toBe('%PDF-bytes');

    Http::assertSent(function (HttpRequest $request) {
        return $request->url() === 'http://gotenberg-test:3000/forms/chromium/convert/html'
            && str_contains((string) $request->body(), '<p>Hallo</p>');
    });
});

it('throws when Gotenberg responds with an error status', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('error', 422)]);

    expect(fn () => (new GotenbergClient)->htmlToPdf('<p>x</p>'))
        ->toThrow(RequestException::class);
});
