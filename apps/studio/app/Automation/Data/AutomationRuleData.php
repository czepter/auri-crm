<?php

namespace App\Automation\Data;

use App\Automation\Models\AutomationRule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A rule as the Automationen screen reads it: what sets it off in plain
 * words, who hears about it, what has to be true, and when it last ran.
 */
#[TypeScript]
class AutomationRuleData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $triggerLabel,
        public string $recipient,
        public string $recipientLabel,
        public ?string $condition,
        public ?string $conditionLabel,
        public bool $isActive,
        public ?string $lastRunAt,
        public string $subject,
        public string $body,
        public ?string $anchor,
        public ?int $offsetDays,
        public ?string $direction,
    ) {}

    public static function fromModel(AutomationRule $rule): self
    {
        return new self(
            id: $rule->public_id,
            name: $rule->name,
            triggerLabel: self::trigger($rule),
            recipient: $rule->recipient_type->value,
            recipientLabel: $rule->recipient_type->label(),
            condition: $rule->condition?->value,
            conditionLabel: $rule->condition?->label(),
            isActive: $rule->is_active,
            lastRunAt: $rule->runs()->max('fired_at'),
            subject: $rule->notification_subject,
            body: $rule->notification_body,
            anchor: $rule->anchor?->value,
            offsetDays: $rule->offset_days,
            direction: $rule->direction?->value,
        );
    }

    /** The trigger as a sentence, because "per_shoot / 7 / before" is three columns of nothing. */
    private static function trigger(AutomationRule $rule): string
    {
        if ($rule->anchor === null) {
            return class_basename((string) $rule->event_class);
        }

        $side = $rule->direction?->value === 'before' ? 'vor' : 'nach';

        return "{$rule->offset_days} Tage {$side}: {$rule->anchor->label()}";
    }
}
