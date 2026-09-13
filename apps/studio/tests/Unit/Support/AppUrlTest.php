<?php

it('gives a bare app host a scheme', function () {
    $_ENV['APP_URL'] = $_SERVER['APP_URL'] = 'meine.example.de';

    try {
        expect((require __DIR__.'/../../../config/app.php')['url'])->toBe('https://meine.example.de');
    } finally {
        unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
    }
});
