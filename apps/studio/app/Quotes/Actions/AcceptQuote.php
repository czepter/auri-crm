<?php

namespace App\Quotes\Actions;

use App\Crm\Models\Contact;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The client's own state change, mirrored in shape off Q-06's SendQuote but
 * triggered by the acting contact rather than staff. The whole point of this
 * story: the re-check below runs against the row as it stands right now, at
 * submit — never against whatever a screen rendered minutes or tabs ago. A
 * quote superseded by a revision, or past its own valid_until, is rejected
 * here even when an earlier GET on the same quote had nothing to object to.
 *
 * Idempotent the same shape App\Contracts\Actions\SignContract's own
 * docblock describes: a doubled request (a retried POST, two tabs) finds the
 * quote already accepted and returns it, rather than failing on the
 * booted() guard's second write or writing a second acceptance.
 */
class AcceptQuote
{
    public function __invoke(Quote $quote, Contact $contact, string $ip): Quote
    {
        if ($quote->status === QuoteStatus::Accepted) {
            return $quote;
        }

        if ($quote->status === QuoteStatus::Superseded) {
            throw ValidationException::withMessages(['quote' => __('portal.quote_superseded')]);
        }

        $expired = $quote->valid_until !== null && $quote->valid_until->copy()->endOfDay()->isPast();

        if ($quote->status !== QuoteStatus::Sent || $expired) {
            throw ValidationException::withMessages(['quote' => __('portal.quote_not_valid')]);
        }

        DB::transaction(function () use ($quote, $contact, $ip) {
            $quote->update([
                'status' => QuoteStatus::Accepted,
                'accepted_at' => now(),
                'accepted_ip' => $ip,
                'accepted_by_contact_id' => $contact->id,
            ]);

            // actor is null, not $contact — AuditLog::actor_id is a foreign
            // key to staff users, and a Contact is never one. The same shape
            // SignContract's own audit call uses.
            AuditLogger::record(
                event: 'quote.accepted',
                context: ['project' => $quote->project->public_id, 'contact' => $contact->public_id],
                subject: $quote->public_id,
                projectId: $quote->project_id,
            );
        });

        return $quote->fresh();
    }
}
