<?php

namespace App\Automation\Data;

use App\Automation\Models\AutomationRun;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AutomationRunData extends Data
{
    public function __construct(
        public string $id,
        public string $firedAt,
        public string $status,
        public string $statusLabel,
        public ?string $error,
    ) {}

    public static function fromModel(AutomationRun $run): self
    {
        return new self(
            id: $run->public_id,
            firedAt: $run->fired_at->format('Y-m-d H:i'),
            status: $run->status->value,
            statusLabel: match ($run->status->value) {
                'sent' => 'Versendet',
                'skipped' => 'Übersprungen',
                'failed' => 'Fehlgeschlagen',
                default => 'Offen',
            },
            error: $run->error,
        );
    }
}
