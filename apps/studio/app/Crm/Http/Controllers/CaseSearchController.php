<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\CaseSuggestionData;
use App\Crm\Models\ClientCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds ProjectTypeahead on the gallery form. Same shape as
 * ContactSearchController, including the ilike: on Postgres like is
 * case-sensitive, and a search that misses "Basthorst" when someone types
 * "basth" sends the user off to create a second gallery in the wrong place.
 */
class CaseSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $query = ClientCase::query()
            ->visibleTo(auth('staff')->user())
            ->limit(8);

        if (mb_strlen($term) < 2) {
            $query->whereNull('completed_at')
                ->where(fn ($open) => $open->booked()->orWhereHas('lead', fn ($lead) => $lead->open()))
                ->latest('updated_at');
        } else {
            $query->where('name', 'ilike', "%{$term}%")->orderBy('name');
        }

        $projects = $query->get();

        return response()->json([
            'projects' => CaseSuggestionData::collect($projects),
        ]);
    }
}
