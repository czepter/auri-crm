<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds the command palette. Same row shape as RecordVisit::recent(), so the
 * palette renders both lists with one template. A project is found by its
 * contacts too: an inquiry is remembered by who wrote, not by its name.
 */
class PaletteSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = "%{$term}%";

        $contacts = Contact::query()
            ->where(fn ($query) => $query
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like))
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Contact $contact) => [
                'kind' => 'kontakt',
                'title' => $contact->name,
                'href' => "/kontakte/{$contact->public_id}",
            ]);

        $projects = Project::query()
            ->visibleTo(auth('staff')->user())
            ->where(fn ($query) => $query
                ->where('name', 'ilike', $like)
                ->orWhereHas('contacts', fn ($contact) => $contact->where(fn ($inner) => $inner
                    ->where('contacts.name', 'ilike', $like)
                    ->orWhere('contacts.email', 'ilike', $like))))
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Project $project) => [
                'kind' => $project->isBooked() ? 'projekt' : 'anfrage',
                'title' => $project->name,
                'href' => "/auftraege/{$project->public_id}",
            ]);

        $galleries = Gallery::query()
            ->with('project')
            ->whereHas('project', fn ($project) => $project->visibleTo(auth('staff')->user()))
            ->where(fn ($query) => $query
                ->where('title', 'ilike', $like)
                ->orWhereHas('project', fn ($project) => $project->where('name', 'ilike', $like)))
            ->orderBy('title')
            ->limit(5)
            ->get()
            ->map(fn (Gallery $gallery) => [
                'kind' => 'galerie',
                'title' => $gallery->title ?? $gallery->project?->name ?? '',
                'href' => "/galerien/{$gallery->public_id}",
            ]);

        return response()->json(['results' => [...$contacts, ...$projects, ...$galleries]]);
    }
}
