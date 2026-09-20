<?php

namespace App\Integration\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A staff user, for an api-surface token to resolve a name into the id assign_crew needs. */
#[TypeScript]
class StaffReadData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->public_id,
            name: $user->name,
            role: $user->role->value,
        );
    }
}
