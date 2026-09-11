<?php

namespace App\Quotes\Actions;

use App\Models\User;
use App\Notifications\NotificationService;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteSent;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The state change this whole sprint hangs off, and the first caller of
 * Q-04's Quote::snapshotVat(). Freezes the amounts Q-03's
 * recalculateTotal() already resolved by doing nothing more to them —
 * Quote's own booted() guard is what makes that freeze real from here on,
 * not a second copy of the numbers taken here. T-04's AllocateDocumentNumber
 * runs at creation now (QuoteController::store()), not here — a quote's
 * number is immutable once set.
 */
class SendQuote
{
    public function __construct(
        private readonly StudioSettingsResolver $settings,
        private readonly NotificationService $notifications,
    ) {}

    public function __invoke(Quote $quote, ?User $actor = null): Quote
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new DomainException("Quote {$quote->public_id} is already {$quote->status->value} and cannot be sent.");
        }

        DB::transaction(function () use ($quote, $actor) {
            // snapshotVat() first, while the row still reads draft in the
            // database: Quote::booted()'s guard only starts refusing writes
            // once status itself is already sent/superseded there, so this
            // order is what lets Q-04's own update() call keep working
            // unchanged from inside here — see that method's docblock.
            $quote->snapshotVat($this->settings);

            $quote->update([
                'status' => QuoteStatus::Sent,
                'sent_at' => now(),
            ]);

            AuditLogger::record(
                event: 'quote.sent',
                actor: $actor,
                context: ['project' => $quote->project->public_id, 'number' => $quote->number],
                subject: $quote->public_id,
                projectId: $quote->project_id,
            );
        });

        $this->mail($quote->fresh());

        return $quote;
    }

    /**
     * Synchronous, deliberately: M-00's queue exists by the time this story
     * ships, but per the issue's own reasoning a single quote email is not
     * worth queueing, so this sends inline in the same request rather than
     * dispatching a job — and outside the DB transaction above, so a mail
     * failure can never roll back a send that already committed.
     *
     * Silent when there is nothing to send to: ProjectFactory attaches no
     * contact by default, and an inquiry-stage project may genuinely have
     * none yet — neither is an error a quote-send should fail on.
     *
     * E-07/T-11: through NotificationService, addressed to the Contact
     * itself rather than Notification::route('mail', ...) — the on-demand
     * shape this used before Contact carried Notifiable. Naming the Contact
     * is what lets T-11's preferences and quiet hours apply; an on-demand
     * address carries no identity either could key on.
     */
    private function mail(Quote $quote): void
    {
        $contact = $quote->project->primaryContact();

        if ($contact === null || blank($contact->email)) {
            return;
        }

        $this->notifications->send($contact, new QuoteSent($quote));
    }
}
