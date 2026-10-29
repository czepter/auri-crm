<?php

namespace App\Quotes\Actions;

use App\Models\StudioSettings;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * A new draft beside an already-accepted quote — the admin store()'s
 * `addendum_to` path (Q-06), extracted so the api surface can reach it too.
 * Unlike ReviseQuote, the predecessor stays accepted: an addendum adds to a
 * booking, it does not replace it.
 */
class CreateQuoteAddendum
{
    public function __construct(
        private readonly AllocateDocumentNumber $allocate,
        private readonly ResolveQuoteRecipient $resolveRecipient,
    ) {}

    public function __invoke(Quote $predecessor): Quote
    {
        if ($predecessor->status !== QuoteStatus::Accepted) {
            throw new DomainException("Quote {$predecessor->public_id} is not accepted and cannot take an addendum.");
        }

        return DB::transaction(function () use ($predecessor) {
            $allocation = ($this->allocate)(documentType: DocumentType::Quote);
            $recipient = ($this->resolveRecipient)($predecessor->case);

            return $predecessor->case->quotes()->create([
                'valid_until' => now()->addDays(StudioSettings::current()->quote_validity_days)->toDateString(),
                'is_addendum' => true,
                'predecessor_id' => $predecessor->id,
                'number' => $allocation['number'],
                'recipient_contact_id' => $recipient?->id,
                'recipient_street' => $recipient?->street,
                'recipient_postcode' => $recipient?->postcode,
                'recipient_city' => $recipient?->city,
            ]);
        });
    }
}
