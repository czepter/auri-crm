<?php

namespace App\Quotes\Support;

use App\Crm\Models\ClientCase;
use App\Documents\Contracts\MergeDataProvider;
use Illuminate\Database\Eloquent\Model;

class QuoteMergeData implements MergeDataProvider
{
    /**
     * What a quote says about itself. Empty on a project with no sent quote —
     * a contract template referencing them is asking about a document that
     * does not exist, which is exactly the case an empty string is right for.
     *
     * Validity and the VAT sentence, frozen on the quote at send (Q-04/Q-06)
     * rather than recomputed here: a client reading a quote must see the rule
     * that applied when it went out, not the one the studio switched to
     * afterwards.
     *
     * @param  ClientCase|null  $case
     * @return array<string, array<string, string>>
     */
    public function blocks(?Model $case): array
    {
        $quote = $case?->latestQuote;

        if ($quote === null) {
            return ['quote' => ['validUntil' => '', 'vatNote' => '']];
        }

        return ['quote' => [
            'validUntil' => $quote->valid_until?->format('d.m.Y') ?? '',
            // A standard-rate quote froze no note — there is nothing unusual
            // to say — so the sentence is composed from the frozen rate. Only
            // the small-business case carries stored wording.
            'vatNote' => $quote->vat_note
                ?? ($quote->vat_rate === null || $quote->vat_rate === 0
                    ? ''
                    : 'Alle Preise verstehen sich zzgl. '.rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',').' % Umsatzsteuer.'),
        ]];
    }
}
