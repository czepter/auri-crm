<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which pipeline a stage belongs to. A workflow's stage is stored as a plain
 * string, and this says which enum reads it back: LeadStage before the booking,
 * ShootStatus after it.
 *
 * Two columns (lead_stage, shoot_status) would let a row carry both and mean
 * neither. One kind plus one value cannot.
 */
#[TypeScript]
enum StageKind: string implements HasLabel
{
    case Lead = 'lead';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Project => 'Projekt',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public function stages(): array
    {
        $cases = match ($this) {
            self::Lead => LeadStage::cases(),
            self::Project => ShootStatus::cases(),
        };

        return array_map(
            fn (LeadStage|ShootStatus $stage) => ['value' => $stage->value, 'label' => $stage->label()],
            $cases,
        );
    }

    public function has(string $stage): bool
    {
        return in_array($stage, array_column($this->stages(), 'value'), true);
    }
}
