<?php

namespace App\Crm\Rules;

use App\Crm\Enums\StagePipeline;
use App\Crm\Support\CrmVocabulary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A stage is only a stage of its own pipeline. task_templates.stage holds the
 * same key leads.stage and shoots.status hold, so nothing but this stops a
 * lead template from carrying "culling".
 */
class StageOfPipeline implements ValidationRule
{
    public function __construct(private readonly ?string $pipeline) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pipeline = StagePipeline::tryFrom((string) $this->pipeline);

        if ($pipeline === null || app(CrmVocabulary::class)->byKey($pipeline, (string) $value) === null) {
            $fail('validation.in')->translate(['attribute' => $attribute]);
        }
    }
}
