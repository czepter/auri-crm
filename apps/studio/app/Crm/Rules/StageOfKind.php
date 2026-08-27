<?php

namespace App\Crm\Rules;

use App\Crm\Enums\StageKind;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A stage is only a stage of its own pipeline. Stored as a plain string, so
 * nothing but this stops a lead template from carrying "culling".
 */
class StageOfKind implements ValidationRule
{
    public function __construct(private readonly ?string $kind) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $kind = StageKind::tryFrom((string) $this->kind);

        if ($kind === null || ! $kind->has((string) $value)) {
            $fail('validation.in')->translate(['attribute' => $attribute]);
        }
    }
}
