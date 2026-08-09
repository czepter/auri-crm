<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs in german', function () {
    expect(config('app.locale'))->toBe('de')
        ->and(config('app.fallback_locale'))->toBe('de');
});

it('renders validation failures in german', function () {
    // Asserted through the translator rather than through the login rules, so
    // this test keeps testing the catalog even if SessionController's rules
    // change.
    expect(__('validation.required', ['attribute' => __('validation.attributes.email')]))
        ->toBe('Das Feld E-Mail muss ausgefüllt werden.');

    $this->post('/login', ['email' => '', 'password' => ''])->assertSessionHasErrors('email');
});

it('renders the throttle message in german', function () {
    expect(__('auth.throttle', ['seconds' => 30]))
        ->toBe('Zu viele Anmeldeversuche. Bitte in 30 Sekunden erneut versuchen.');
});
