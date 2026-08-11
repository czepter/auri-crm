<?php

namespace App\Settings\Support;

/**
 * Whether the bank fieldset must be present on save. Bank details are
 * mandatory as of T-07: the portal payment screen always renders
 * "Überweisen" with the studio's IBAN, whether or not Stripe is enabled, so
 * a studio with no bank details on file has nothing correct to show a
 * client asked to pay by transfer.
 */
class BankDetailsRequirement
{
    public static function isRequired(): bool
    {
        return true;
    }
}
