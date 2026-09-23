<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Project;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Support\Collection;

/**
 * #417: the ladder in FibonacciReminderSchedule starts after the due date,
 * so the studio hears about a rate for the first time when it is already
 * late. This is the rung before it — three days out, once per installment.
 *
 * Built over a collection like InstallmentOverdue next door, and for the
 * same reason: two rates falling due the same week are one occasion.
 *
 * @param  Collection<int, PaymentInstallment>  $installments
 */
class InstallmentDueSoon extends StudioEventNotification
{
    public function __construct(private readonly Collection $installments) {}

    public function title(): string
    {
        return 'Raten werden fällig';
    }

    public function body(): string
    {
        return implode(' · ', $this->lines());
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->installments
            ->map(fn (PaymentInstallment $installment) => sprintf(
                '%s — %s, fällig am %s',
                $installment->plan->project->name,
                number_format($installment->remaining() / 100, 2, ',', '.').' €',
                $installment->due_on->format('d.m.Y'),
            ))
            ->values()
            ->all();
    }

    public function url(): string
    {
        return $this->project() === null
            ? '/auftraege'
            : "/auftraege/{$this->project()->public_id}";
    }

    public function project(): ?Project
    {
        $projects = $this->installments->map(fn (PaymentInstallment $i) => $i->plan->project)->unique('id');

        return $projects->count() === 1 ? $projects->first() : null;
    }
}
