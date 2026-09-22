<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use App\Integration\Data\ContactReadData;
use App\Integration\Data\LeadReadData;
use App\Integration\Data\ProjectDetailReadData;
use App\Integration\Data\ProjectMoneyReadData;
use App\Integration\Data\ProjectReadData;
use App\Integration\Data\ShootReadData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Validation\Rule;
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
        $term = $request->validate(['query' => ['sometimes', 'string', 'max:255']])['query'] ?? null;

        // ilike, not like: on Postgres like is case-sensitive, and a search
        // that misses "Steffens" when the caller sends "steff" is the same
        // duplicate ContactSearchController's typeahead exists to prevent.
        $query = Contact::query()->when($term, fn (Builder $q, string $term) => $q->where(
            fn (Builder $match) => $match->where('name', 'ilike', "%{$term}%")->orWhere('email', 'ilike', "%{$term}%")
        ));

        return $this->page($query, $request, ContactReadData::class);
    }

    public function projects(Request $request): JsonResponse
    {
        return $this->page(Project::query()->whereHas('job'), $request, ProjectReadData::class);
    }

    /**
     * N-02: the single-resource read the index alone can't answer — "get a
     * project" needs the project, not a page that might contain it. Route
     * model binding does the tenant + public_id lookup (and the 404 for a
     * project outside the token's tenant) the same as every other
     * `{project:public_id}` route on this surface already does.
     */
    public function project(Project $project): JsonResponse
    {
        return response()->json(['data' => ProjectDetailReadData::fromModel($project)]);
    }

    /** #402: the payment plan ProjectDetailReadData leaves out. */
    public function money(Project $project): JsonResponse
    {
        return response()->json(['data' => ProjectMoneyReadData::fromModel($project)]);
    }

    /**
     * `closed` rather than a stage key for the end of the funnel — a
     * closed lead keeps the stage it died in, so the two are independent
     * questions. Absent, both open and closed leads come back.
     */
    public function leads(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'stage' => ['sometimes', Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Lead->value)],
            // Strings, not `boolean`: that rule refuses the literal "false"
            // every http client writes into a query string.
            'closed' => ['sometimes', 'in:true,false'],
            'captured_from' => ['sometimes', 'date'],
            'captured_to' => ['sometimes', 'date', 'after_or_equal:captured_from'],
        ]);

        $query = Lead::query()
            ->with('project:id,public_id,name')
            ->when($filters['stage'] ?? null, fn (Builder $q, string $stage) => $q->where('stage', $stage))
            ->when($request->has('closed'), fn (Builder $q) => $request->boolean('closed')
                ? $q->whereNotNull('outcome')
                : $q->open())
            ->when($filters['captured_from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('captured_at', '>=', $from))
            ->when($filters['captured_to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('captured_at', '<=', $to));

        return $this->page($query, $request, LeadReadData::class);
    }

    /**
     * N-02: `from`/`to` narrow this to a date range — the query "which
     * shoots fall in March" — on top of I-02's plain `since` staleness
     * filter, which the shared page() below still applies. Both bounds are
     * inclusive and optional independently, so a caller can ask for
     * everything from a date onward, or up to one, as well as a closed range.
     */
    public function shoots(Request $request): JsonResponse
    {
        $range = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'cancelled' => ['sometimes', 'in:true,false'],
        ]);

        // Omitted leaves cancelled shoots in: a `since=` poller that has seen
        // a shoot must still see its absage, so the filter is opt-in on both
        // sides rather than a default the caller cannot notice. Strings
        // because this is a query string — Laravel's `boolean` rule rejects
        // the literal "false" every http client writes for it.
        $cancelled = array_key_exists('cancelled', $range) ? $request->boolean('cancelled') : null;

        $query = Shoot::query()
            ->with(['project:id,public_id', 'crew.user'])
            ->when($range['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('date', '>=', $from))
            ->when($range['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('date', '<=', $to))
            ->when($cancelled !== null, fn (Builder $q) => $cancelled ? $q->whereNotNull('cancelled_at') : $q->uncancelled());

        return $this->page($query, $request, ShootReadData::class);
    }

    /**
     * N-02: the funnel, not the leads that make it up — a count per
     * stage rather than a page of Lead rows the caller would have to
     * tally itself. Same grouping order as LeadBoardController's own board
     * (declaration order, not the stored strings' alphabetical order) and
     * the same `open()` scope: a lost lead left its stage the moment it was
     * marked lost, so it no longer belongs to any column's count.
     */
    public function pipelineSnapshot(): JsonResponse
    {
        $counts = Lead::query()
            ->open()
            ->select('stage')
            ->selectRaw('count(*) as count')
            ->groupBy('stage')
            ->get()
            ->mapWithKeys(fn (Lead $lead) => [$lead->stage->key => (int) $lead->count]);

        $stages = app(CrmVocabulary::class)->ordered(StagePipeline::Lead)
            ->filter(fn (Stage $stage) => $stage->is_active)
            ->map(fn (Stage $stage) => [
                'stage' => $stage->key,
                'label' => $stage->name,
                'count' => $counts[$stage->key] ?? 0,
            ])->all();

        return response()->json(['data' => $stages]);
    }

    /**
     * @param  class-string<Data>  $dataClass  Must expose a static fromModel().
     */
    private function page(Builder $query, Request $request, string $dataClass): JsonResponse
    {
        $filters = $request->validate([
            'since' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var CursorPaginator $paginator */
        $paginator = $query
            ->when($filters['since'] ?? null, fn (Builder $q, string $since) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->cursorPaginate($filters['per_page'] ?? 25);

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
