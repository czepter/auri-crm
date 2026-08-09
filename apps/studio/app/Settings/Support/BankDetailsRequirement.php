<?php

namespace App\Settings\Support;

/**
 * Whether the bank fieldset must be present on save. Bank details only
 * become mandatory once the studio actually offers clients a transfer
 * option — a decision T-07 (not built yet) owns. Named and reusable so that
 * story flips the one return value below rather than rewriting
 * StudioSettingsController's validation rules.
 */
class BankDetailsRequirement
{
    public static function isRequired(): bool
    {
        return false;
    }
}
