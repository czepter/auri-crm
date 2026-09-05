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

    // Uniform for an unknown address, an expired link and an already-accepted
    // one, for the same reason 'failed' is uniform.
    'invitation_invalid' => 'Diese Einladung ist nicht mehr gültig. Bitte im Studio nach einer neuen fragen.',

    // Said the same way whether or not the address has an account.
    'reset_sent' => 'Falls ein Konto zu dieser Adresse gehört, ist die E-Mail unterwegs.',
    'reset_done' => 'Passwort geändert. Jetzt anmelden — die Bestätigung in zwei Schritten bleibt erforderlich.',
    'password' => 'Das angegebene Passwort ist nicht korrekt.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte in :seconds Sekunden erneut versuchen.',

    // Uniform for a wrong code, an expired one and a spent recovery code —
    // which of the three it was is not something to tell the browser.
    'mfa_invalid' => 'Dieser Code ist nicht gültig.',

    // Der Bestätigungslink aus der Profil-Mail — abgelaufen, verbraucht und
    // nie existiert sind dieselbe Antwort.
    'link_dead' => 'Dieser Link gilt nicht mehr. Bitte die Adresse erneut ändern.',
    'email_taken' => 'Diese Adresse wird inzwischen von einem anderen Zugang benutzt.',

];
