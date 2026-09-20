<?php

namespace App\Settings\Support;

/**
 * Registernummer und Registergericht gibt es nur für Rechtsformen, die in
 * einem Register stehen. Einzelunternehmen und GbR stehen in keinem, und ein
 * Pflichtfeld, das es für sie nicht gibt, ist eine Rechnung, die niemand
 * ausstellen kann.
 *
 * Dieselbe Liste führt resources/js/admin/lib/legalForm.ts für die
 * Formularseite — zwei Laufzeiten, eine Regel; LegalFormTest hält sie
 * zusammen.
 */
class LegalForm
{
    /** @var list<string> */
    public const REGISTERED = [
        'GmbH',
        'UG (haftungsbeschränkt)',
        'AG',
        'e.K.',
        'OHG',
        'KG',
        'GmbH & Co. KG',
        'PartG',
    ];

    public static function requiresRegister(?string $legalForm): bool
    {
        return $legalForm !== null && in_array($legalForm, self::REGISTERED, true);
    }
}
