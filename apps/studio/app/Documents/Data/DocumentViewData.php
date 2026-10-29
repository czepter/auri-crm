<?php

namespace App\Documents\Data;

use App\Documents\Models\DocumentView;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DocumentViewData extends Data
{
    public function __construct(
        public string $id,
        public string $viewedAt,
        public string $via,
        public string $device,
        public ?string $network,
        public bool $counts,
    ) {}

    public static function fromModel(DocumentView $view): self
    {
        return new self(
            id: $view->public_id,
            viewedAt: $view->viewed_at->toIso8601String(),
            via: $view->via,
            device: self::device($view->user_agent ?? ''),
            network: $view->ip_hash === null ? null : substr($view->ip_hash, 0, 6),
            counts: $view->counts,
        );
    }

    private static function device(string $agent): string
    {
        $system = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            preg_match('/Edg(e|A|iOS)?\//', $agent) === 1 => 'Edge',
            preg_match('/OPR\/|Opera/', $agent) === 1 => 'Opera',
            preg_match('/Firefox\/|FxiOS/', $agent) === 1 => 'Firefox',
            preg_match('/Chrome\/|CriOS/', $agent) === 1 => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        return implode(' · ', array_filter([$system, $browser]));
    }
}
