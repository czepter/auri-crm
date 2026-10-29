<?php

namespace App\Crm\Screens;

use App\Crm\Actions\DeriveCasePosition;
use App\Crm\Models\ClientCase;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;

/**
 * C-11 (#247): the scope of the project screen as it actually stands. The
 * accepted quote is read once, and the divergence rule below has an interface a
 * test can reach without a request.
 */
class ReadProjectScreen
{
    /** @var array<int, ?Quote> */
    private array $accepted = [];

    public function __construct(private readonly DeriveCasePosition $position) {}

    /**
     * What was ordered against what stands now.
     *
     * The accepted quote is untouched; the current state is a draft quote at
     * the same project. Every line that differs is marked, the old value stays
     * under the new one, and the strip states the difference in money.
     *
     * @return array{items: list<array<string, mixed>>, delta: int, hasDraft: bool}
     */
    public function scope(ClientCase $project): array
    {
        $accepted = $this->accepted($project);
        $draft = $project->quotes()->where('status', QuoteStatus::Draft)->latest('id')->first();
        $current = $draft ?? $accepted;

        if ($current === null) {
            return ['items' => [], 'delta' => 0, 'hasDraft' => false];
        }

        $before = $accepted?->items->keyBy('title') ?? collect();

        return [
            'items' => $current->items->map(function ($item) use ($before, $draft) {
                $was = $before->get($item->title);

                return [
                    'id' => $item->public_id,
                    'title' => $item->title,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                    // Only a draft can diverge: without one, what stands is
                    // what was ordered.
                    'state' => $draft === null || $was !== null && (int) $was->unit_price === (int) $item->unit_price
                        ? null
                        : ($was === null ? 'new' : 'changed'),
                    'wasUnitPrice' => $was?->unit_price,
                ];
            })->all(),
            'delta' => $accepted === null || $draft === null ? 0 : (int) $draft->total - (int) $accepted->total,
            'hasDraft' => $draft !== null,
        ];
    }

    private function accepted(ClientCase $project): ?Quote
    {
        return $this->accepted[$project->id] ??= $project->quotes()
            ->where('status', QuoteStatus::Accepted)
            ->latest('id')
            ->first();
    }
}
