<?php

namespace App\Integration\Data;

use App\Crm\Models\CrewAssignment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Who is on a shoot, for an api-surface token. Requires `user` already eager loaded. */
#[TypeScript]
class CrewAssignmentReadData extends Data
{
    public function __construct(
        public string $id,
        public string $shootId,
        public string $userId,
        public string $userName,
        public string $role,
    ) {}

    public static function fromModel(CrewAssignment $assignment): self
    {
        return new self(
            id: $assignment->public_id,
            shootId: $assignment->shoot->public_id,
            userId: $assignment->user->public_id,
            userName: $assignment->user->name,
            role: $assignment->role->value,
        );
    }
}
