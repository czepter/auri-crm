<?php

namespace App\Quotes\Actions;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\StageAnchor;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The state change SendQuote used to own outright, now shared with the
 * self-sent path (T-11's WhatsApp/own-mail delivery) so neither leaves the
 * quote without Q-04's VAT snapshot — see SendQuote's own docblock for why
 * that snapshot cannot be skipped on either path.
 */
class MarkQuoteSent
{
    public function __construct(
        private readonly StudioSettingsResolver $settings,
        private readonly AdvanceLeadStage $leads,
    ) {}

    /** $at: when it went out, for a quote entered after the fact. */
    public function __invoke(Quote $quote, string $channel, ?User $actor = null, ?CarbonInterface $at = null): Quote
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new DomainException("Quote {$quote->public_id} is already {$quote->status->value} and cannot be sent.");
        }

        DB::transaction(function () use ($quote, $channel, $actor, $at) {
            // snapshotVat() first, while the row still reads draft in the
            // database: Quote::booted()'s guard only starts refusing writes
            // once status itself is already sent/superseded there, so this
            // order is what lets the update() call below keep working
            // unchanged — see Quote::snapshotVat()'s own docblock.
            $quote->snapshotVat($this->settings);

            $quote->update([
                'status' => QuoteStatus::Sent,
                'sent_at' => $at ?? now(),
                'sent_channel' => $channel,
            ]);

            AuditLogger::record(
                event: 'quote.sent',
                actor: $actor,
                context: ['project' => $quote->project->public_id, 'number' => $quote->number, 'channel' => $channel],
                subject: $quote->public_id,
                projectId: $quote->project_id,
                occurredAt: $at,
            );

            $this->leads->reach($quote->project->lead, StageAnchor::QuoteSent, $actor, $at);
        });

        return $quote;
    }
}
