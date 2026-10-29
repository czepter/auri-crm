<?php

namespace App\Quotes\Actions;

use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use DomainException;

/**
 * A new draft beside an already-accepted quote (Q-06). Unlike ReviseQuote,
 * the predecessor stays accepted: an addendum adds to a booking, it does not
 * replace it.
 */
class CreateQuoteAddendum
{
    public function __construct(
        private readonly CreateQuote $create,
    ) {}

    public function __invoke(Quote $predecessor, ?User $actor = null): Quote
    {
        if ($predecessor->status !== QuoteStatus::Accepted) {
            throw new DomainException("Quote {$predecessor->public_id} is not accepted and cannot take an addendum.");
        }

        return ($this->create)($predecessor->case, $actor, predecessor: $predecessor);
    }
}
