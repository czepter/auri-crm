<?php

namespace App\Portal\Http\Controllers;

use App\Documents\Actions\RecordDocumentView;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\InlineHtmlSanitizer;
use App\Documents\Support\MergeFieldResolver;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Portal\Support\LegalTexts;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Data\QuoteData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's read of the quote Q-06 sent, and this story's own accept
 * action beside it. Project::latestQuote() is what both actions ask —
 * Q-04/Q-06's own frozen snapshot, never re-derived from the tenant's live
 * settings — so what renders here is exactly the document a client is
 * accepting.
 *
 * Asks Contact::visibleProjects() the same question ContractController does,
 * so a project the contact is not on and one the studio has not made
 * portal_visible fail identically, as a 404 rather than a 403.
 */
class QuoteController extends Controller
{
    public function show(Request $request, string $case, RecordDocumentView $record): Response
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('cases.public_id', $case)
            ->with(['latestQuote.items', 'latestQuote.predecessor', 'latestQuote.paymentStages', 'primaryShoot'])
            ->firstOrFail();
        $quote = $found->latestQuote;

        if ($quote !== null) {
            $record($request, $quote, 'portal');
        }

        return Inertia::render('Quotes/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => [
                'id' => $found->public_id,
                'name' => $found->name,
                'date' => $found->primaryShoot?->date?->format('Y-m-d'),
                'venue' => $found->primaryShoot?->venue(),
                'href' => "/projekte/{$found->public_id}",
            ],
            // T-08's own switch (default off): the affordance half of "accept
            // quotes online", the accept() route below is the authorisation
            // half via `portal.capability:accept_quotes`. Never hides an
            // already-accepted quote — only the button that would accept one.
            'canAccept' => (bool) StudioSettings::current()->portal_accept_quotes_enabled,
            'quote' => $quote === null ? null : [
                ...QuoteData::fromModel($quote->asSeenByClient())->toArray(),
                // #480: the letter as the client reads it — fields filled, escaped.
                ...collect(['intro' => $quote->intro, 'outro' => $quote->outro])->map(fn (?string $html) => MergeFieldResolver::resolve(
                    InlineHtmlSanitizer::letter($html) ?? '',
                    app(DocumentMergeData::class)->forCase($quote->case),
                    escape: true,
                ))->all(),
                // Display only — see AcceptQuote for the re-check this
                // flag has no say over. A stale tab keeps reading whatever
                // it rendered; the server never trusts it back.
                'expired' => $quote->valid_until !== null && $quote->valid_until->copy()->endOfDay()->isPast(),
                'acceptedAt' => $quote->accepted_at?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Q-10: the picture on a block's tile, as the client sees it. Served
     * through the project the contact may see rather than by path: the file
     * itself lives beside every other tenant's under catalog/, and a
     * guessable filename in a URL would be the whole access rule.
     */
    /** Q-10: eine Kachel des Reglerrasters, ueber denselben Weg wie image() darunter. */
    public function showcase(string $case, int $index): StreamedResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('cases.public_id', $case)->firstOrFail();

        $path = $found->latestQuote?->showcasePaths()[$index] ?? null;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }

    public function image(string $case, string $item): StreamedResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('cases.public_id', $case)
            ->with('latestQuote.items')
            ->firstOrFail();

        $path = $found->latestQuote?->items->firstWhere('public_id', $item)?->image_path;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * The submit. AcceptQuote re-reads status and valid_until off the row
     * fetched fresh by this request — never off what the earlier GET above
     * handed the screen — so a quote invalidated in the gap between the two
     * requests is refused here regardless of what was on screen.
     */
    public function accept(Request $request, string $case, AcceptQuote $accept): RedirectResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('cases.public_id', $case)
            ->with('latestQuote')
            ->firstOrFail();

        abort_if($found->latestQuote === null, 404);

        // Q-12: a proposal, not a decision. AcceptQuote checks the hours
        // against the quote's own bounds and refuses a line the studio never
        // made switchable — validating the shape here would only be a second
        // place to keep those rules.
        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:0'],
            'items' => ['array'],
            'items.*' => ['string'],
            // The acceptance stands in for a contract, so the AGB are part of
            // it wherever the studio attaches any.
            'terms' => Rule::when(LegalTexts::attachedUrls() !== [], ['accepted']),
        ], ['terms.accepted' => __('portal.quote_terms_required')]);

        $accept(
            $found->latestQuote,
            $contact,
            (string) $request->ip(),
            $validated['hours'] ?? null,
            $validated['items'] ?? [],
        );

        return back();
    }
}
