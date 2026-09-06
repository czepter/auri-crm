<?php

namespace App\Automation\Data;

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRun;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One firing, as the rule's own history reads it. */
#[TypeScript]
class AutomationRunData extends Data
{
    public function __construct(
        public string $id,
        public string $firedAt,
        public string $anchorLabel,
        public string $status,
        public string $statusLabel,
    ) {}

    public static function fromModel(AutomationRun $run): self
    {
        return new self(
            id: $run->public_id,
            firedAt: match (true) {
                $run->fired_at->isToday() => 'Heute '.$run->fired_at->format('H:i'),
                $run->fired_at->isYesterday() => 'Gestern '.$run->fired_at->format('H:i'),
                default => $run->fired_at->format('d.m. H:i'),
            },
            anchorLabel: self::anchor($run),
            status: $run->status->value,
            statusLabel: match ($run->status) {
                AutomationRunStatus::Sent => 'Gesendet',
                AutomationRunStatus::Skipped => 'Übersprungen',
                AutomationRunStatus::Failed => 'Fehlgeschlagen',
                AutomationRunStatus::Pending => 'Offen',
            },
        );
    }

    /** What it fired against, and — where it did not send — what stopped it. */
    private static function anchor(AutomationRun $run): string
    {
        $anchor = $run->anchorable;
        $name = $anchor === null ? '—' : (FireAutomationRule::projectOf($anchor)?->name ?? '—');

        return $run->error === null ? $name : "{$name} · {$run->error}";
    }
}
