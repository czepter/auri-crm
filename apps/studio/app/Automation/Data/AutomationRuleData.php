<?php

namespace App\Automation\Data;

use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A rule as the Automationen screen reads it: what sets it off in plain
 * words, who hears about it, what has to be true, and how the last day went.
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
        public ?string $lastRunLabel,
        public string $subject,
        public string $body,
        public string $triggerType,
        public ?string $eventLabel,
        public ?string $anchor,
        public ?int $offsetDays,
        public ?string $direction,
        public ?string $projectType,
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
            lastRunLabel: self::lastRun($rule),
            subject: $rule->notification_subject,
            body: $rule->notification_body,
            triggerType: $rule->trigger_type->value,
            eventLabel: $rule->event_class === null ? null : class_basename($rule->event_class),
            anchor: $rule->anchor?->value,
            offsetDays: $rule->offset_days,
            direction: $rule->direction?->value,
            projectType: $rule->project_type,
        );
    }

    /** The trigger as a sentence, because "per_shoot / 7 / before" is three columns of nothing. */
    private static function trigger(AutomationRule $rule): string
    {
        if ($rule->anchor === null) {
            return 'Bei '.class_basename((string) $rule->event_class);
        }

        $side = $rule->direction?->value === 'before' ? 'vor' : 'nach';
        $unit = $rule->offset_days === 1 ? 'Tag' : 'Tage';

        return "{$rule->offset_days} {$unit} {$side}: {$rule->anchor->label()}";
    }

    /**
     * The last day this rule did anything, and what came of it. Null where it
     * has never run — the column shows an em dash rather than a sentence
     * about the absence.
     */
    private static function lastRun(AutomationRule $rule): ?string
    {
        $last = $rule->runs()->latest('fired_at')->first();

        if ($last === null) {
            return null;
        }

        $day = match (true) {
            $last->fired_at->isToday() => 'Heute '.$last->fired_at->format('H:i'),
            $last->fired_at->isYesterday() => 'Gestern '.$last->fired_at->format('H:i'),
            default => $last->fired_at->format('d.m. H:i'),
        };

        $sent = $rule->runs()
            ->whereDate('fired_at', $last->fired_at->toDateString())
            ->where('status', AutomationRunStatus::Sent)
            ->count();

        return $sent > 0
            ? "{$day} · {$sent} gesendet"
            : "{$day} · ".self::statusLabel($last);
    }

    private static function statusLabel(AutomationRun $run): string
    {
        return match ($run->status) {
            AutomationRunStatus::Skipped => 'übersprungen',
            AutomationRunStatus::Failed => 'fehlgeschlagen',
            default => 'offen',
        };
    }
}
