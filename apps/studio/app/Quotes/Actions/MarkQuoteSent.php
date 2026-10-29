<?php

namespace App\Quotes\Actions;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\StageAnchor;
use App\Models\User;
use App\Portal\Support\PortalSettings;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The state change SendQuote used to own outright, now shared with the
 * self-sent path (T-11's WhatsApp/own-mail delivery) so neither leaves the
 * quote without Q-04's VAT snapshot — see SendQuote's own docblock for why
 * that snapshot cannot be skipped on either path.
 */
class MarkQuoteSent
{
    public function __construct(
        private readonly AdvanceLeadStage $leads,
    ) {}

    /** $at: when it went out, for a quote entered after the fact. */
    public function __invoke(Quote $quote, string $channel, ?User $actor = null, ?CarbonInterface $at = null): Quote
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new DomainException("Quote {$quote->public_id} is already {$quote->status->value} and cannot be sent.");
        }

        // Ein Angebot darf entstehen, ohne dass die Widerrufsbelehrung steht;
        // hinausgehen darf es nicht. Ohne sie beginnt die Frist eines
        // Verbrauchers nicht zu laufen (§ 356 Abs. 3 BGB), und das gilt
        // unabhängig davon, ob das Angebot als Mail oder über WhatsApp
        // rausgeht — deshalb steht die Prüfung hier und nicht in SendQuote.
        if (PortalSettings::legalUrl('widerruf') === null) {
            throw ValidationException::withMessages(['quote' => __('quotes.widerrufMissing')]);
        }

        DB::transaction(function () use ($quote, $channel, $actor, $at) {
            // snapshotVat() first, while the row still reads draft in the
            // database: Quote::booted()'s guard only starts refusing writes
            // once status itself is already sent/superseded there, so this
            // order is what lets the update() call below keep working
            // unchanged — see Quote::snapshotVat()'s own docblock.
            $quote->snapshotVat();

            $quote->update([
                'status' => QuoteStatus::Sent,
                'sent_at' => $at ?? now(),
                'sent_channel' => $channel,
            ]);

            AuditLogger::record(
                event: 'quote.sent',
                actor: $actor,
                context: ['project' => $quote->case->public_id, 'number' => $quote->number, 'channel' => $channel],
                subject: $quote->public_id,
                projectId: $quote->case_id,
                occurredAt: $at,
            );

            $this->leads->reach($quote->case->lead, StageAnchor::QuoteSent, $actor, $at);
        });

        return $quote;
    }
}
