<?php

namespace App\Crm\Actions;

use App\Crm\Models\Project;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;

/**
 * C-11 (#247): the two derived halves of the project screen — the key figures
 * and the scope as it actually stands.
 *
 * Both read the accepted quote, so both used to fetch it. Here it is read once
 * and the divergence rule below has an interface a test can reach without a
 * request.
 */
class ReadProjectScreen
{
    /** @var array<int, ?Quote> */
    private array $accepted = [];

    public function __construct(private readonly DeriveProjectPosition $position) {}

    /**
     * Five figures, each with the line that proves it: where the project
     * stands, the next shooting day, what was ordered, what is still open, the
     * portal — each with one line of proof under it.
     *
     * Two fields the draft asks for are deliberately not invented. `Phase` as
     * a maintained value does not exist — it is derived (DeriveProjectPosition)
     * and stays derived. And the follow-up date is a task with a due date, so
     * it needs no column of its own: the row in "Offen" is the whole feature.
     *
     * @return array<string, array{value: ?string, proof: ?string}>
     */
    public function figures(Project $project): array
    {
        $accepted = $this->accepted($project);
        $plan = $project->job?->paymentPlan;
        $open = $plan === null ? null : (int) $plan->installments()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->sum('amount');

        return [
            'phase' => [
                'value' => ($this->position)($project)->label,
                'proof' => $project->lead?->source?->label(),
            ],
            'nextDate' => [
                'value' => $project->shoots()->uncancelled()->whereNotNull('date')
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')->value('date')?->format('Y-m-d')
                    // All of them behind us: the day that was, not a dash.
                    ?? $project->primaryShoot?->date?->format('Y-m-d'),
                'proof' => null,
            ],
            'value' => [
                'value' => $accepted === null ? null : (string) $accepted->total,
                'proof' => $accepted?->number,
            ],
            'open' => [
                'value' => $open === null ? null : (string) $open,
                'proof' => $plan === null ? null : (string) $plan->installments()->count(),
            ],
            'portal' => [
                'value' => $project->portal_visible ? 'on' : 'off',
                'proof' => null,
            ],
        ];
    }

    /**
     * What was ordered against what stands now.
     *
     * The accepted quote is untouched; the current state is a draft quote at
     * the same project. Every line that differs is marked, the old value stays
     * under the new one, and the strip states the difference in money.
     *
     * @return array{items: list<array<string, mixed>>, delta: int, hasDraft: bool}
     */
    public function scope(Project $project): array
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

    private function accepted(Project $project): ?Quote
    {
        return $this->accepted[$project->id] ??= $project->quotes()
            ->where('status', QuoteStatus::Accepted)
            ->latest('id')
            ->first();
    }
}
