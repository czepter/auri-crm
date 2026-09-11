<?php

namespace App\Crm\Actions;

use App\Crm\Data\ProjectPositionData;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Stage;
use App\Crm\Support\CrmVocabulary;
use Illuminate\Support\Collection;

/**
 * The way from inquiry to delivery as one row of steps, the position marked.
 *
 * Read off the anchors, so a stage a studio inserts shows up here unasked.
 * The inquiry's won stage and the project's booked stage are the same fact —
 * both read "Gebucht" — so the inquiry stops before its won anchor and the
 * project starts at its booked one. Archived comes after the work and is not
 * a step.
 */
class ReadStageTrack
{
    public function __construct(private readonly CrmVocabulary $stages) {}

    /** @return list<array{key: string, label: string, pipeline: string, state: string}> */
    public function __invoke(ProjectPositionData $position): array
    {
        $won = $this->sortOf(StagePipeline::Lead, StageAnchor::Won);
        $booked = $this->stages->anchored(StagePipeline::Project, StageAnchor::Booked);
        $archived = $this->sortOf(StagePipeline::Project, StageAnchor::Archived);

        $steps = $this->steps(StagePipeline::Lead, fn (Stage $stage) => $stage->sort_order < $won)
            ->concat($this->steps(
                StagePipeline::Project,
                fn (Stage $stage) => $stage->sort_order >= ($booked?->sort_order ?? 0) && $stage->sort_order < $archived,
            ))
            ->values();

        [$pipeline, $key] = match ($position->source) {
            'shoot' => [StagePipeline::Project->value, $position->value],
            'lead' => ($this->stages->byKey(StagePipeline::Lead, (string) $position->value)?->sort_order ?? -1) >= $won
                ? [StagePipeline::Project->value, $booked?->key]
                : [StagePipeline::Lead->value, $position->value],
            default => [null, null],
        };

        $at = $steps->search(fn (array $step) => $step['pipeline'] === $pipeline && $step['key'] === $key);

        return $steps->map(fn (array $step, int $index) => [...$step, 'state' => match (true) {
            $at === false || $index > $at => 'todo',
            $index === $at => 'current',
            default => 'done',
        }])->all();
    }

    /** @return Collection<int, array{key: string, label: string, pipeline: string}> */
    private function steps(StagePipeline $pipeline, callable $keep): Collection
    {
        return $this->stages->ordered($pipeline)
            ->filter(fn (Stage $stage) => $stage->is_active && $keep($stage))
            ->map(fn (Stage $stage) => ['key' => $stage->key, 'label' => $stage->name, 'pipeline' => $pipeline->value]);
    }

    private function sortOf(StagePipeline $pipeline, StageAnchor $anchor): int
    {
        return $this->stages->anchored($pipeline, $anchor)?->sort_order ?? PHP_INT_MAX;
    }
}
