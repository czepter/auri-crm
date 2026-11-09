<?php

namespace App\Quotes\Actions;

use App\Models\User;
use App\Notifications\NotificationService;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteFollowUp;
use App\Support\AuditLogger;
use DomainException;

class FollowUpQuote
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function __invoke(Quote $quote, User $actor, string $subject, string $message): void
    {
        if ($quote->status !== QuoteStatus::Sent) {
            throw new DomainException("Quote {$quote->public_id} is {$quote->status->value}, only a sent quote can be followed up.");
        }

        $contact = $quote->case->primaryContact();

        if ($contact === null || blank($contact->email)) {
            return;
        }

        $this->notifications->send($contact, new QuoteFollowUp($quote, $subject, $message));

        AuditLogger::record(
            event: 'quote.followed_up',
            actor: $actor,
            context: ['project' => $quote->case->public_id, 'number' => $quote->number],
            subject: $quote->public_id,
            projectId: $quote->case_id,
        );
    }
}
