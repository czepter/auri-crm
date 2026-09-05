<?php

namespace App\Data;

use App\Models\Absence;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One recorded absence as the Abwesenheiten screen reads it. `canManage` is
 * AbsencePolicy's own "mine, or I hold ManageCrew" question, decided once
 * here rather than the template re-deriving ownership from a raw id
 * comparison it has no business making.
 */
#[TypeScript]
class AbsenceData extends Data
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $userName,
        public string $category,
        public string $categoryLabel,
        public string $startsOn,
        public string $endsOn,
        public bool $canManage,
    ) {}

    public static function fromModel(Absence $absence, User $viewer): self
    {
        return new self(
            id: $absence->public_id,
            userId: $absence->user->public_id,
            userName: $absence->user->name,
            category: $absence->category->value,
            categoryLabel: $absence->category->label(),
            startsOn: $absence->starts_on->format('Y-m-d'),
            endsOn: $absence->ends_on->format('Y-m-d'),
            canManage: $viewer->can('update', $absence),
        );
    }
}
