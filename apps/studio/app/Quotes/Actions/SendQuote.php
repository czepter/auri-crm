<?php

namespace App\Quotes\Actions;

use App\Models\User;
use App\Notifications\NotificationService;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteSent;
use Carbon\CarbonInterface;

/**
 * The state change this whole sprint hangs off, and the first caller of
 * Q-04's Quote::snapshotVat(). Freezes the amounts Q-03's
 * recalculateTotal() already resolved by doing nothing more to them —
 * Quote's own booted() guard is what makes that freeze real from here on,
 * not a second copy of the numbers taken here. T-04's AllocateDocumentNumber
 * runs at creation now (QuoteController::store()), not here — a quote's
 * number is immutable once set. The guard, transaction, VAT snapshot and
 * audit log now live in MarkQuoteSent, shared with the self-sent path; this
 * class only adds the client mail on top, channel 'mail'.
 */
class SendQuote
{
    public function __construct(
        private readonly MarkQuoteSent $mark,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * $at: when it went out, for a quote entered after the fact. $subject
     * and $message: the compose screen's own fields (QuoteController::
     * compose()) — both optional, and QuoteSent falls back to its own
     * defaults when neither rides along.
     */
    public function __invoke(
        Quote $quote,
        ?User $actor = null,
        ?CarbonInterface $at = null,
        ?string $subject = null,
        ?string $message = null,
    ): Quote {
        ($this->mark)($quote, 'mail', $actor, $at);

        $this->mail($quote->fresh(), $subject, $message);

        return $quote;
    }

    /**
     * Synchronous, deliberately: M-00's queue exists by the time this story
     * ships, but per the issue's own reasoning a single quote email is not
     * worth queueing, so this sends inline in the same request rather than
     * dispatching a job — and outside the DB transaction above, so a mail
     * failure can never roll back a send that already committed.
     *
     * Silent when there is nothing to send to: ClientCaseFactory attaches no
     * contact by default, and an inquiry-stage project may genuinely have
     * none yet — neither is an error a quote-send should fail on.
     *
     * E-07/T-11: through NotificationService, addressed to the Contact
     * itself rather than Notification::route('mail', ...) — the on-demand
     * shape this used before Contact carried Notifiable. Naming the Contact
     * is what lets T-11's preferences apply; an on-demand address carries
     * no identity they could key on.
     */
    private function mail(Quote $quote, ?string $subject, ?string $message): void
    {
        $contact = $quote->case->primaryContact();

        if ($contact === null || blank($contact->email)) {
            return;
        }

        $this->notifications->send($contact, new QuoteSent($quote, $subject, $message));
    }
}
