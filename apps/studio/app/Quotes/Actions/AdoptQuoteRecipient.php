<?php

namespace App\Quotes\Actions;

use App\Quotes\Models\Quote;

/** Writes the quote's own address back onto its recipient contact. */
class AdoptQuoteRecipient
{
    public function __invoke(Quote $quote): void
    {
        $quote->recipientContact->update([
            'street' => $quote->recipient_street,
            'postcode' => $quote->recipient_postcode,
            'city' => $quote->recipient_city,
        ]);
    }
}
