<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Data\QuoteData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
    public function show(string $project, StudioSettingsResolver $settings): Response
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('projects.public_id', $project)
            ->with(['latestQuote.items', 'latestQuote.predecessor'])
            ->firstOrFail();
        $quote = $found->latestQuote;

        return Inertia::render('Quotes/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            // T-08's own switch (default off): the affordance half of "accept
            // quotes online", the accept() route below is the authorisation
            // half via `portal.capability:accept_quotes`. Never hides an
            // already-accepted quote — only the button that would accept one.
            'canAccept' => $settings->acceptQuotesEnabled(),
            'quote' => $quote === null ? null : [
                ...QuoteData::fromModel($quote)->toArray(),
                // Display only — see AcceptQuote for the re-check this
                // flag has no say over. A stale tab keeps reading whatever
                // it rendered; the server never trusts it back.
                'expired' => $quote->valid_until !== null && $quote->valid_until->copy()->endOfDay()->isPast(),
                'acceptedAt' => $quote->accepted_at?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * The submit. AcceptQuote re-reads status and valid_until off the row
     * fetched fresh by this request — never off what the earlier GET above
     * handed the screen — so a quote invalidated in the gap between the two
     * requests is refused here regardless of what was on screen.
     */
    public function accept(Request $request, string $project, AcceptQuote $accept): RedirectResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('projects.public_id', $project)
            ->with('latestQuote')
            ->firstOrFail();

        abort_if($found->latestQuote === null, 404);

        $accept($found->latestQuote, $contact, (string) $request->ip());

        return back();
    }
}
