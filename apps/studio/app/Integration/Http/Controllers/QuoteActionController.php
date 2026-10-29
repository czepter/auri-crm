<?php

namespace App\Integration\Http\Controllers;

use App\Catalog\Actions\SavePackage;
use App\Catalog\Models\Package;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Quotes\Actions\CreatePackageFromQuote;
use App\Quotes\Actions\CreateQuoteAddendum;
use App\Quotes\Actions\DuplicateQuote;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Data\QuoteData;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * #403: the admin-only quote actions the api surface lacked — addendum,
 * duplicate, delete (drafts), mark-sent-by-self, adopt-recipient and
 * save-as-package. Each is the same Action or inline logic the admin
 * QuoteController already runs (App\Quotes\Http\Controllers\QuoteController),
 * called without a staff session — see each method's own doc comment for
 * which one.
 */
class QuoteActionController extends Controller
{
    /** The admin store()'s `addendum_to` path (CreateQuoteAddendum), for an already-accepted quote. */
    public function addendum(Request $request, Quote $quote, CreateQuoteAddendum $create): JsonResponse
    {
        $addendum = $this->attempt(fn () => $create($quote));

        AuditLogger::record(
            event: 'quote.created',
            actor: $this->actor($request),
            context: ['project' => $quote->case->public_id, 'addendum_to' => $quote->public_id],
            subject: $addendum->public_id,
            projectId: $quote->case_id,
        );

        return $this->answer($addendum, 201);
    }

    /** QuoteController::duplicate() — a new independent draft, never the source's history. */
    public function duplicate(Request $request, Quote $quote, DuplicateQuote $duplicate): JsonResponse
    {
        $copy = $duplicate($quote);

        AuditLogger::record(
            event: 'quote.duplicated',
            actor: $this->actor($request),
            context: ['from' => $quote->public_id],
            subject: $copy->public_id,
            projectId: $quote->case_id,
        );

        return $this->answer($copy, 201);
    }

    /** QuoteController::destroy() — only a draft; a sent quote may have already reached the client. */
    public function destroy(Request $request, Quote $quote): Response
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw ValidationException::withMessages([
                'quote' => "Quote {$quote->public_id} is {$quote->status->value} and cannot be deleted. Only a draft can.",
            ]);
        }

        AuditLogger::record(
            event: 'quote.deleted',
            actor: $this->actor($request),
            context: ['number' => $quote->number],
            subject: $quote->public_id,
            projectId: $quote->case_id,
        );

        $quote->delete();

        return response()->noContent();
    }

    /** QuoteController::markSent() — delivered by the studio itself, channel 'self', no notification. */
    public function markSent(Request $request, Quote $quote, MarkQuoteSent $mark): JsonResponse
    {
        $this->attempt(fn () => $mark($quote, 'self', $this->actor($request)));

        return $this->answer($quote);
    }

    /** QuoteController::adoptRecipient() — writes the quote's own address back onto its recipient contact. */
    public function adoptRecipient(Quote $quote): JsonResponse
    {
        abort_if($quote->recipient_contact_id === null, 404);

        $quote->recipientContact->update([
            'street' => $quote->recipient_street,
            'postcode' => $quote->recipient_postcode,
            'city' => $quote->recipient_city,
        ]);

        return $this->answer($quote);
    }

    /**
     * QuoteController::saveAsPackage()/saveItemAsPackage() — the only way to
     * write a package's hourly rate and payment plan from the api surface.
     * `item`, when given, is one line's public id and only that line is
     * saved (SavePackage, the board's own single-item path); otherwise the
     * whole quote (CreatePackageFromQuote).
     */
    public function saveAsPackage(Request $request, Quote $quote, CreatePackageFromQuote $create, SavePackage $save): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'item' => ['nullable', 'string'],
        ]);

        $quote->loadMissing('items');

        if (isset($validated['item'])) {
            $item = $quote->items->firstWhere('public_id', $validated['item']);

            if ($item === null) {
                throw ValidationException::withMessages(['item' => "Quote {$quote->public_id} has no such line."]);
            }

            $package = $save(new Package, [
                'name' => $validated['name'],
                'items' => [$item->only(['title', 'description', 'quantity', 'unit_price', 'selectable', 'selected', 'outcome'])],
            ]);
        } else {
            if ($quote->items->isEmpty()) {
                throw ValidationException::withMessages(['name' => 'The quote has no lines to save as a package.']);
            }

            $package = $create($quote, $validated['name']);
        }

        AuditLogger::record(
            event: 'package.created',
            actor: $this->actor($request),
            context: ['name' => $package->name, 'source' => isset($validated['item']) ? 'quote_item' : 'quote'],
            subject: $package->public_id,
        );

        return response()->json(['data' => $package->public_id], 201);
    }

    private function attempt(callable $act): mixed
    {
        try {
            return $act();
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['quote' => $e->getMessage()]);
        }
    }

    private function answer(Quote $quote, int $status = 200): JsonResponse
    {
        return response()->json(['data' => QuoteData::fromModel($quote->fresh(['items', 'paymentStages', 'predecessor']))], $status);
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
