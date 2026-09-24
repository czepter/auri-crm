<?php

namespace App\Notifications\Studio;

use App\Crm\Models\ClientCase;
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
                $installment->plan->case->name,
                number_format($installment->remaining() / 100, 2, ',', '.').' €',
                $installment->due_on->format('d.m.Y'),
            ))
            ->values()
            ->all();
    }

    public function url(): string
    {
        return $this->case() === null
            ? '/auftraege'
            : "/auftraege/{$this->case()->public_id}";
    }

    public function case(): ?ClientCase
    {
        $projects = $this->installments->map(fn (PaymentInstallment $i) => $i->plan->case)->unique('id');

        return $projects->count() === 1 ? $projects->first() : null;
    }
}
