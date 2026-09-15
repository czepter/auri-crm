<?php

return [
    // Steht am Rechnungs-Button, weil das die einzige Stelle ist, an der die
    // Angabe fällig wird — gefragt wird sie beim Einrichten nicht.
    'taxIdentifierMissing' => 'Steuernummer oder USt-IdNr. fehlt',

    // #408: eine bezahlte Rate ist ein Fall für die Gutschrift, nicht für
    // den Storno — welcher von beiden, sagt der Vertrag.
    'cancelPaidRefused' => 'Bezahlte Rate: Gutschrift statt Storno',
    'cancelNotInvoiced' => 'Nur eine gestellte Rechnung lässt sich stornieren',
    'cancelledNotIssuable' => 'Stornierte Rate',
];
