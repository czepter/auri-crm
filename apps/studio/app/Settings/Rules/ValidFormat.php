<?php

namespace App\Settings\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Spiegel von resources/js/lib/rules.ts: was das Formular live meldet, lehnt
 * auch der Server ab. Leer bleibt Sache von nullable und required_without.
 */
class ValidFormat implements ValidationRule
{
    private const MESSAGES = [
        'bic' => 'Ungültige BIC',
        'vat_id' => 'Ungültige USt-IdNr.',
        'tax_number' => 'Ungültige Steuernummer',
    ];

    /** @param 'bic'|'vat_id'|'tax_number' $format */
    public function __construct(private readonly string $format) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes(trim($value))) {
            $fail(self::MESSAGES[$this->format]);
        }
    }

    private function passes(string $value): bool
    {
        return match ($this->format) {
            'bic' => (bool) preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper(preg_replace('/\s/', '', $value))),
            'vat_id' => $this->vatId(strtoupper(preg_replace('/[\s.-]/', '', $value))),
            'tax_number' => (bool) preg_match('/^[0-9\/\s]+$/', $value)
                && preg_match('/^\d{10,13}$/', preg_replace('/[\/\s]/', '', $value)) === 1,
        };
    }

    private function vatId(string $id): bool
    {
        return str_starts_with($id, 'DE')
            ? preg_match('/^DE[0-9]{9}$/', $id) === 1
            : preg_match('/^[A-Z]{2}[A-Z0-9]{2,12}$/', $id) === 1;
    }
}
