<?php

namespace App\Integration\Data;

use App\Crm\Models\Job;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A booking as I-02's read endpoints hand it to an api-surface token.
 * public_id only, for both the job and the project it hangs off — never
 * either row's internal id.
 */
#[TypeScript]
class JobReadData extends Data
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $bookedAt,
        public string $bookedVia,
        public string $updatedAt,
    ) {}

    /** Requires `project:id,public_id` already eager loaded — see CrmReadController. */
    public static function fromModel(Job $job): self
    {
        return new self(
            id: $job->public_id,
            projectId: $job->project->public_id,
            bookedAt: $job->booked_at->toIso8601String(),
            bookedVia: $job->booked_via->value,
            updatedAt: $job->updated_at->toIso8601String(),
        );
    }
}
