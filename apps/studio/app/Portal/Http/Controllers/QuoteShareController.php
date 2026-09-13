<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Portal\Support\PortalSettings;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Data\QuoteData;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The "abgespecktes Portal" a quote's own share link opens: the same
 * Quotes/Show.vue a logged-in contact sees on QuoteController, but reached by
 * the quote's own share_token rather than a portal session — no contact, no
 * portal_visible, no login. share_token is the whole access rule, the same
 * way a GalleryDownloadLink token is its download screen's.
 */
class QuoteShareController extends Controller
{
    public function show(Quote $quote, StudioSettingsResolver $settings): Response
    {
        $quote->loadMissing(['items', 'predecessor', 'paymentStages', 'project', 'recipientContact']);

        return Inertia::render('Quotes/Show', [
            'contact' => ['name' => ''],
            'showSwitcher' => false,
            'project' => ['id' => $quote->project->public_id, 'name' => $quote->project->name],
            'canAccept' => $settings->acceptQuotesEnabled(),
            'showcaseCount' => count($settings->showcasePaths()),
            'acceptUrl' => "/angebot/{$quote->share_token}/teilen/annehmen",
            'imageUrlBase' => "/angebot/{$quote->share_token}/teilen",
            'quote' => [
                ...QuoteData::fromModel($quote)->toArray(),
                'expired' => $quote->valid_until !== null && $quote->valid_until->copy()->endOfDay()->isPast(),
                'acceptedAt' => $quote->accepted_at?->format('Y-m-d'),
            ],
        ]);
    }

    public function image(Quote $quote, string $item): StreamedResponse
    {
        $path = $quote->items->firstWhere('public_id', $item)?->image_path;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }

    public function accept(Request $request, Quote $quote, AcceptQuote $accept, PortalSettings $settings): RedirectResponse
    {
        $contact = $quote->recipientContact;

        abort_if($contact === null, 404);
        abort_unless($settings->acceptQuotesEnabled($contact), 404);

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:0'],
            'items' => ['array'],
            'items.*' => ['string'],
        ]);

        $accept(
            $quote,
            $contact,
            (string) $request->ip(),
            $validated['hours'] ?? null,
            $validated['items'] ?? [],
        );

        return back();
    }
}
