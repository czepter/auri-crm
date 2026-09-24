<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ProjectSuggestionData;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds ProjectTypeahead on the gallery form. Same shape as
 * ContactSearchController, including the ilike: on Postgres like is
 * case-sensitive, and a search that misses "Basthorst" when someone types
 * "basth" sends the user off to create a second gallery in the wrong place.
 */
class ProjectSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $query = Project::query()
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
            'projects' => ProjectSuggestionData::collect($projects),
        ]);
    }
}
