<?php

namespace App\Quotes\Actions;

use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Events\QuoteRevised;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Support\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * "A revision is a new row carrying its predecessor's id, and the
 * predecessor becomes superseded in the same transaction as the new row
 * insert" — the issue's own words. A sent quote is never edited back to
 * draft: a client who accepted a specific document is owed that exact
 * document, so a change becomes a second quote instead, and the first stays
 * exactly as it was sent (Quote::booted()'s guard is what makes that hold).
 *
 * Ships unwired: nothing calls this yet. The staff action that triggers a
 * revision — "the client asked for a change to a quote already sent" — has
 * no screen of its own in this story's mockup (04-angebot shows only the
 * post-acceptance state), so wiring a route and a button here would be
 * inventing UI the issue never asked for. Q-07 is the next story that
 * touches this screen and is the natural place to wire it in.
 */
class ReviseQuote
{
    public function __invoke(Quote $predecessor, ?User $actor = null): Quote
    {
        if ($predecessor->status !== QuoteStatus::Sent) {
            throw new DomainException("Quote {$predecessor->public_id} is {$predecessor->status->value} and cannot be revised.");
        }

        return DB::transaction(function () use ($predecessor, $actor) {
            $revision = $predecessor->project->quotes()->create([
                'predecessor_id' => $predecessor->id,
                'valid_until' => $predecessor->valid_until,
                'discount_type' => $predecessor->discount_type,
                'discount_value' => $predecessor->discount_value,
            ]);

            $revision->items()->createMany(
                $predecessor->items->map(fn (QuoteItem $item) => [
                    'title' => $item->title,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sort_order' => $item->sort_order,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ])->all()
            );

            $revision->recalculateTotal();

            // The one write Quote::booted()'s guard still allows on a sent
            // quote: status alone, to superseded.
            $predecessor->update(['status' => QuoteStatus::Superseded]);

            AuditLogger::record(
                event: 'quote.revised',
                actor: $actor,
                context: ['project' => $predecessor->project->public_id, 'predecessor' => $predecessor->public_id],
                subject: $revision->public_id,
            );

            // Dispatched last, inside the transaction — see this event's own
            // docblock for why.
            QuoteRevised::dispatch($predecessor, $revision);

            return $revision;
        });
    }
}
