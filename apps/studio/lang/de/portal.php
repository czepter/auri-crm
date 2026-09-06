<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client portal
    |--------------------------------------------------------------------------
    |
    | link_sent is deliberately non-committal. It is the answer whether the
    | address is known, unknown or rate-limited, because any difference would
    | turn the sign-in form into a way of asking the studio who its clients
    | are.
    |
    */

    'link_sent' => 'Wenn ein Zugang zu dieser Adresse gehört, ist die E-Mail unterwegs. Der Link gilt 15 Minuten.',

    // Uniform for expired, already used and never existed: all three mean the
    // same thing to whoever is holding the link.
    'link_dead' => 'Dieser Link gilt nicht mehr. Bitte einen neuen anfordern.',

    // Q-07: App\Quotes\Actions\AcceptQuote's own re-check, named separately
    // rather than one shared message — a client who watches a quote go
    // through a revision is owed a different sentence than one who simply
    // waited too long.
    'quote_not_valid' => 'Dieses Angebot ist nicht mehr gültig.',
    'quote_superseded' => 'Dieses Angebot wurde bereits durch eine neuere Version ersetzt.',

    // Q-12: der Vorschlag des Reglers, zurueckgewiesen. Beide Faelle sind
    // erreichbar, ohne dass jemand am Bildschirm etwas falsch macht — ein
    // Tab, der offen stand, waehrend das Studio das Angebot ueberarbeitet hat.
    'quote_hours_out_of_range' => 'Die gewählte Stundenzahl liegt außerhalb des Angebots.',
    'quote_item_not_selectable' => 'Diese Auswahl gehört nicht zum Angebot.',

    // P-03 (#222): the line UpdateProjectDetails writes into the thread. A
    // change to a wedding date that lives only in a diff is how a
    // photographer turns up on the wrong Saturday.
    'details_changed_subject' => 'Angaben geändert',
    'details_changed_body' => "Wir haben unsere Angaben geändert.\n\nWunschtermin: :date\nOrt: :venue\n\n:text",
    'date_open' => 'noch offen',

    // P-09 (#228): a preview reads and nothing else — see EnsureAudience.
    'preview_is_read_only' => 'In der Vorschau sind keine Änderungen möglich.',

    // P-05 (#224): the confirmation link's own dead end. Someone else may
    // have claimed the address between the request and the click.
    'email_taken' => 'Diese Adresse wird inzwischen von einem anderen Zugang benutzt.',
];
