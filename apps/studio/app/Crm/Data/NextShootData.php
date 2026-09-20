<?php

namespace App\Crm\Data;

use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The next shooting day, as the Übersicht leads with it.
 *
 * `readiness` is the mockup's ✓/✗ row, and every mark is a real fact: the crew
 * mark reads the assignments, and the rest are this shoot's own tasks. The
 * mockup invents four labels; inventing them here would make the block a
 * status report about nothing, which is the failure clickdummy §6 records for
 * this screen.
 */
#[TypeScript]
class NextShootData extends Data
{
    /** @param  list<array{label: string, done: bool}>  $readiness */
    public function __construct(
        public string $projectId,
        public string $projectName,
        public string $shootId,
        public string $title,
        public string $date,
        public ?string $callTime,
        public ?string $endTime,
        public ?string $venue,
        public string $crew,
        public array $readiness,
    ) {}

    public static function fromShoot(Shoot $shoot): self
    {
        $crew = $shoot->crew->map(fn ($assignment) => $assignment->user->name);

        return new self(
            projectId: $shoot->project->public_id,
            projectName: $shoot->project->name,
            shootId: $shoot->public_id,
            title: $shoot->label ?? $shoot->kind,
            date: $shoot->date->format('Y-m-d'),
            callTime: $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
            endTime: $shoot->end_time === null ? null : substr((string) $shoot->end_time, 0, 5),
            venue: $shoot->venue(),
            crew: $crew->join(', '),
            readiness: [
                ['label' => 'Crew', 'done' => $crew->isNotEmpty()],
                ...$shoot->tasks
                    ->map(fn (Task $task) => ['label' => $task->title, 'done' => $task->isDone()])
                    ->all(),
            ],
        );
    }
}
