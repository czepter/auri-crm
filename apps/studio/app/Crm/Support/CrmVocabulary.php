<?php

namespace App\Crm\Support;

use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Stage;
use Illuminate\Support\Collection;

/**
 * Every stage and every project type, read once per request.
 *
 * The board turns fifty leads into fifty stage lookups and the project list
 * does the same for statuses, so the cast behind Lead::$stage and
 * Shoot::$status resolves through here rather than issuing a query per row.
 *
 * Memoised in the container, not in Redis: the table is fourteen rows the
 * studio edits by hand a few times a year, and a shared cache would need an
 * invalidation path across web and worker for a query that costs less than
 * the cache lookup. Stage::saved clears it (App\Providers\AppServiceProvider)
 * so a rename lands within the same request that wrote it.
 */
class CrmVocabulary
{
    /** @var Collection<int, Stage>|null */
    private ?Collection $stages = null;

    /** @var Collection<int, ProjectType>|null */
    private ?Collection $types = null;

    public function forget(): void
    {
        $this->stages = null;
        $this->types = null;
    }

    /** @return Collection<int, ProjectType> */
    public function types(): Collection
    {
        return $this->types ??= ProjectType::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function type(string $key): ?ProjectType
    {
        return $this->types()->first(fn (ProjectType $type) => $type->key === $key);
    }

    /** @return Collection<int, Stage> */
    public function ordered(StagePipeline $pipeline): Collection
    {
        return $this->all()
            ->filter(fn (Stage $stage) => $stage->pipeline === $pipeline)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
    }

    public function byKey(StagePipeline $pipeline, string $key): ?Stage
    {
        return $this->all()->first(
            fn (Stage $stage) => $stage->pipeline === $pipeline && $stage->key === $key
        );
    }

    public function anchored(StagePipeline $pipeline, StageAnchor $anchor): ?Stage
    {
        return $this->all()->first(
            fn (Stage $stage) => $stage->pipeline === $pipeline && $stage->anchor === $anchor
        );
    }

    /**
     * Where a project stage sits for the screens that group by it: before the
     * shoot, between shoot and delivery, or delivered. Read off the anchors,
     * because the studio renames and reorders the stages themselves.
     *
     * @return 'upcoming'|'editing'|'delivered'
     */
    public function phase(Stage $stage): string
    {
        $from = fn (StageAnchor $anchor) => $this->anchored(StagePipeline::Project, $anchor)?->sort_order ?? PHP_INT_MAX;

        return match (true) {
            $stage->sort_order >= $from(StageAnchor::Delivered) => 'delivered',
            $stage->sort_order >= $from(StageAnchor::Shot) => 'editing',
            default => 'upcoming',
        };
    }

    /** @return Collection<int, Stage> */
    private function all(): Collection
    {
        return $this->stages ??= Stage::query()->orderBy('sort_order')->orderBy('id')->get();
    }
}
