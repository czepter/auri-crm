<?php

namespace App\Crm\Data;

use App\Crm\Models\CrewAssignment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One person on one shoot. Carries initials rather than an avatar URL: users
 * has no avatar column and S-02 does not add one, so the initials are the
 * avatar rather than a placeholder for one that failed to load.
 */
#[TypeScript]
class CrewMemberData extends Data
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $name,
        public string $initials,
    ) {}

    public static function fromAssignment(CrewAssignment $assignment): self
    {
        return new self(
            id: $assignment->public_id,
            userId: $assignment->user->public_id,
            name: $assignment->user->name,
            initials: $assignment->user->initials(),
        );
    }
}
