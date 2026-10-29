<?php

namespace App\Quotes\Actions;

use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use DomainException;

class DeleteQuote
{
    /** Only a draft: a sent quote may already be in the client's hands. */
    public function __invoke(Quote $quote, ?User $actor = null): void
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new DomainException("Quote {$quote->public_id} is {$quote->status->value} and cannot be deleted. Only a draft can.");
        }

        AuditLogger::record(
            event: 'quote.deleted',
            actor: $actor,
            context: ['number' => $quote->number],
            subject: $quote->public_id,
            projectId: $quote->case_id,
        );

        $quote->delete();
    }
}
