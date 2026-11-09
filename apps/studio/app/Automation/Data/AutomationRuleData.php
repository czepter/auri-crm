<?php

namespace App\Automation\Data;

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use Illuminate\Support\Facades\Lang;
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
        public bool $lastRunFailed,
        public string $subject,
        public string $body,
        public string $triggerType,
        public ?string $eventLabel,
        public ?string $anchor,
        public ?int $offsetDays,
        public ?string $direction,
        public ?string $projectType,
        public string $action,
        public int $drafts,
        public string $group,
        public int $order,
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
            lastRunFailed: $rule->runs()->latest('fired_at')->first()?->status === AutomationRunStatus::Failed,
            subject: $rule->notification_subject,
            body: $rule->notification_body,
            triggerType: $rule->trigger_type->value,
            eventLabel: $rule->event_class === null ? null : self::event($rule->event_class),
            anchor: $rule->anchor?->value,
            offsetDays: $rule->offset_days,
            direction: $rule->direction?->value,
            projectType: $rule->project_type,
            action: $rule->action->value,
            drafts: $rule->drafts_count ?? 0,
            group: self::placement($rule)['group'],
            order: self::placement($rule)['order'],
        );
    }

    private static function event(string $class): string
    {
        $name = class_basename($class);

        return Lang::has("settings.events.{$name}") ? __("settings.events.{$name}") : $name;
    }

    /** The trigger as a sentence, because "per_shoot / 7 / before" is three columns of nothing. */
    private static function trigger(AutomationRule $rule): string
    {
        if ($rule->anchor === null) {
            $name = class_basename((string) $rule->event_class);

            return Lang::has("settings.event_clauses.{$name}") ? rtrim(__("settings.event_clauses.{$name}"), ',') : $name;
        }

        if ($rule->offset_days === 0) {
            return "Am {$rule->anchor->at()}";
        }

        $side = $rule->direction?->value === 'before' ? 'vor' : 'nach';
        $unit = $rule->offset_days === 1 ? 'Tag' : 'Tage';

        return "{$rule->offset_days} {$unit} {$side} {$rule->anchor->noun()}";
    }

    /**
     * Where the rule sits along the project. Within an anchor, a countdown
     * sorts before the date and a wait after it.
     *
     * @return array{group: string, order: int}
     */
    private static function placement(AutomationRule $rule): array
    {
        if ($rule->anchor === null) {
            $event = __('settings.event_groups.'.class_basename((string) $rule->event_class));

            return ['group' => $event['group'], 'order' => $event['order'] * 1000 + 500];
        }

        $days = $rule->offset_days * ($rule->direction?->value === 'before' ? -1 : 1);

        if (in_array($rule->anchor, [AutomationAnchor::PerShoot, AutomationAnchor::PrimaryShoot], true) && $days > 0) {
            return ['group' => 'Nachher', 'order' => 90_000 + 500 + $days];
        }

        return ['group' => $rule->anchor->group(), 'order' => $rule->anchor->order() * 1000 + 500 + $days];
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
