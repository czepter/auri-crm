<?php

return [
    // Steht am Rechnungs-Button, weil das die einzige Stelle ist, an der die
    // Angabe fällig wird — gefragt wird sie beim Einrichten nicht.
    'taxIdentifierMissing' => 'Steuernummer oder USt-IdNr. fehlt',

    // #408: eine bezahlte Rate ist ein Fall für die Gutschrift, nicht für
    // den Storno — welcher von beiden, sagt der Vertrag.
    'cancelPaidRefused' => 'Bezahlte Rechnung: Gutschrift statt Storno',
    'cancelNotInvoiced' => 'Nur eine gestellte Rechnung lässt sich stornieren',
    'cancelledNotIssuable' => 'Stornierte Rate',
    'alreadyInvoiced' => 'Rate bereits gestellt',
    'invoiceOnePlan' => 'Raten eines Zahlungsplans wählen',
    'finalTakesNoPositions' => 'Endrechnung ohne weitere Positionen',
    'previewNumber' => 'Entwurf',
    'previewRecipient' => 'Empfänger',
    'invoiceStatus' => [
        'open' => 'Offen',
        'partial' => 'Teilzahlung',
        'overdue' => 'Überfällig',
        'paid' => 'Bezahlt',
        'cancelled' => 'Storniert',
    ],

    // #409: eine unbezahlte Rechnung wird storniert, nicht gutgeschrieben.
    'creditNoteNotPaid' => 'Nur eine bezahlte Rechnung lässt sich gutschreiben',
    'creditNoteTooLarge' => 'Höchstens :amount € gutschreibbar',
    'creditNoteNoStripePayment' => 'Keine Stripe-Zahlung zur Rückerstattung',

    // #418: die Gründe, die auf Storno und Gutschrift landen, wenn der Plan
    // als Ganzes abgewickelt wird.
    'settleAlreadySettled' => 'Der Zahlungsplan ist abgewickelt',
    'settleVoidReason' => 'Shooting abgesagt, Zahlungsplan abgewickelt',
    'settleCreditReason' => 'Shooting abgesagt, Anzahlung erstattet',
];
