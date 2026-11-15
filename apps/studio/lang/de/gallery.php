<?php

return [

    /*
    |--------------------------------------------------------------------------
    | G-06's three gates
    |--------------------------------------------------------------------------
    */

    'password_wrong' => 'Dieser Link und dieses Passwort gehören nicht zusammen.',
    'pin_wrong' => 'Dieser Code stimmt nicht.',

    'email_link_sent' => 'Bestätigungslink verschickt — meist nach ein bis zwei Minuten da.',

    // Uniform for expired, already used and never existed — the same reason
    // portal.link_dead makes no distinction either.
    'email_link_dead' => 'Dieser Bestätigungslink funktioniert nicht mehr.',

    // G-01 (#229): what a comment from somebody with no account is signed.
    'guest' => 'Gast',

    /*
    |--------------------------------------------------------------------------
    | Sets (C-19 media tab)
    |--------------------------------------------------------------------------
    */

    'last_set' => 'Die Galerie braucht ein Set.',

    /*
    |--------------------------------------------------------------------------
    | Veröffentlichen
    |--------------------------------------------------------------------------
    */

    'empty_draft' => 'Die Galerie hat noch keine Bilder.',

    'copy_suffix' => 'Kopie',
    'home_redirect_required' => 'Pflichtfeld',
    'home_redirect_scheme' => 'Nur http- und https-Adressen',
    'home_redirect_self' => 'Zeigt auf :host selbst',
    'favorites_task' => 'Auswahl bearbeiten: :title',
    'prerelease_suffix' => 'Vorabversion',
    'deleted_note' => 'Galerie „:title“ gelöscht. Favoriten und Kommentare: :path',

    'mail_password' => 'Passwort: :password',

    /*
    |--------------------------------------------------------------------------
    | Pre-release
    |--------------------------------------------------------------------------
    */

    'prerelease_unavailable' => 'Pre-release ist nur vor der ersten Veröffentlichung möglich.',
    'prerelease_signup_refused' => 'Eintrag nicht möglich.',
];
