<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds the typeahead on the capture form.
 *
 * The ordering that typeahead enforces — search before you type — is the
 * anti-duplicate mechanism, not a convenience: portal access in P2 hangs off
 * Contact, so a repeat client must resolve to the same row.
 */
class ContactSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['contacts' => []]);
        }

        $contacts = Contact::query()
            ->withCount('projects')
            // ilike, not like: on Postgres like is case-sensitive, and a search
            // that misses "Steffens" when someone types "steff" produces
            // exactly the duplicate contact this screen exists to prevent.
            ->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%"))
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json([
            'contacts' => ContactSuggestionData::collect($contacts),
        ]);
    }
}
