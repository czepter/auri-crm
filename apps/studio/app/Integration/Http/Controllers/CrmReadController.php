<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Http\Controllers\Controller;
use App\Integration\Data\ContactReadData;
use App\Integration\Data\JobReadData;
use App\Integration\Data\LeadReadData;
use App\Integration\Data\ProjectReadData;
use App\Integration\Data\ShootReadData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Spatie\LaravelData\Data;

/**
 * I-02: read-only pages over the CRM core for an api-surface token — the
 * whole shape an external polling loop needs, and nothing it can write.
 *
 * Every route cursor-paginates rather than offset-paginates: a caller that
 * pages the full board and then polls `since=` must never see a row twice
 * just because an earlier page's row moved between requests, which is
 * exactly what an offset skips. Cursors on `id` — already the indexed,
 * monotonic primary key, so paging never needs a second index for it.
 *
 * One controller for all five reads rather than five near-identical files:
 * every action is the same shape (optional `since` filter, cursor page,
 * map to that resource's *ReadData) and only the query and the Data class
 * differ — see page() below.
 */
class CrmReadController extends Controller
{
    public function contacts(Request $request): JsonResponse
    {
        return $this->page(Contact::query(), $request, ContactReadData::class);
    }

    public function projects(Request $request): JsonResponse
    {
        return $this->page(Project::query(), $request, ProjectReadData::class);
    }

    public function leads(Request $request): JsonResponse
    {
        return $this->page(Lead::query()->with('project:id,public_id'), $request, LeadReadData::class);
    }

    public function shoots(Request $request): JsonResponse
    {
        return $this->page(Shoot::query()->with('project:id,public_id'), $request, ShootReadData::class);
    }

    public function jobs(Request $request): JsonResponse
    {
        return $this->page(Job::query()->with('project:id,public_id'), $request, JobReadData::class);
    }

    /**
     * @param  class-string<Data>  $dataClass  Must expose a static fromModel().
     */
    private function page(Builder $query, Request $request, string $dataClass): JsonResponse
    {
        $since = $request->validate(['since' => ['sometimes', 'date']])['since'] ?? null;

        /** @var CursorPaginator $paginator */
        $paginator = $query
            ->when($since, fn (Builder $q) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->cursorPaginate(25);

        // Captured before through() below replaces every item with its
        // *ReadData: a cursor is built from whatever `id` property the
        // paginator's *current* items expose at the moment it's asked for,
        // and every *ReadData's own `id` is a public_id — a different value
        // than the `id` column this query actually orders and cursors by.
        $next = $paginator->nextCursor();
        $prev = $paginator->previousCursor();

        return response()->json([
            'data' => $paginator->through([$dataClass, 'fromModel'])->items(),
            'meta' => [
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $next?->encode(),
                'next_page_url' => $next ? $paginator->url($next) : null,
                'prev_cursor' => $prev?->encode(),
                'prev_page_url' => $prev ? $paginator->url($prev) : null,
            ],
        ]);
    }
}
