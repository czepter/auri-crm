<?php

namespace App\Settings\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * IBAN's own check digits (ISO 7064 MOD 97-10) — not merely its length or
 * character set. A transposed digit pair inside an otherwise well-formed
 * IBAN passes any length/regex check and still fails at the bank; this is
 * what T-02's acceptance criterion asks to catch on save instead.
 *
 * https://en.wikipedia.org/wiki/International_Bank_Account_Number#Validating_the_IBAN
 */
class ValidIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::checksumPasses($value)) {
            $fail('Diese IBAN ist ungültig — die Prüfsumme stimmt nicht.');
        }
    }

    /**
     * Rearrange (move the first four characters to the end), convert every
     * letter to its two-digit number (A=10 … Z=35), then reduce the whole
     * numeric string mod 97 one digit at a time — the remainder stays a
     * small int throughout, so this needs no bignum library for an IBAN of
     * any real-world length.
     */
    public static function checksumPasses(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_digit($char) ? $char : (string) (ord($char) - 55);
        }

        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
