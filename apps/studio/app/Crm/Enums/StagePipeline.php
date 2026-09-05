<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which of the two funnels a stage belongs to. They stay apart for the reason
 * LeadStage and ShootStatus stayed apart: one list of fourteen would carry
 * transitions that are illegal in both directions.
 */
#[TypeScript]
enum StagePipeline: string implements HasLabel
{
    case Lead = 'lead';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Anfrage',
            self::Project => 'Projekt',
        };
    }

    /** Backwards is legitimate on the funnel, and a lie on the pipeline (see LeadStage, ShootStatus). */
    public function allowsBackwards(): bool
    {
        return $this === self::Lead;
    }
}
