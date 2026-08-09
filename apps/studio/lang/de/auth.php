<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | Deliberately uniform: "failed" never distinguishes an unknown address
    | from a wrong password, because the difference is what turns a login form
    | into an account-enumeration oracle.
    |
    */

    'failed' => 'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.',
    'password' => 'Das angegebene Passwort ist nicht korrekt.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte in :seconds Sekunden erneut versuchen.',

    // Uniform for a wrong code, an expired one and a spent recovery code —
    // which of the three it was is not something to tell the browser.
    'mfa_invalid' => 'Dieser Code ist nicht gültig.',

];
