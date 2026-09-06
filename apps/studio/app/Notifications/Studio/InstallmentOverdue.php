<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Project;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Support\Collection;

/**
 * T-22: money that has not arrived. Built like InstallmentPaid next door,
 * but over a collection — three overdue installments on one project are one
 * occasion, not three.
 *
 * The reason travels with it: markOverdue() is called both by the due-date
 * scan and by Stripe's async_payment_failed, and a recipient reading
 * "überfällig" about a client whose card was declined calls to chase a
 * payment that was attempted.
 *
 * @param  Collection<int, PaymentInstallment>  $installments
 */
class InstallmentOverdue extends StudioEventNotification
{
    public function __construct(private readonly Collection $installments) {}

    public function title(): string
    {
        return 'Offene Raten';
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
                '%s — %s, fällig am %s (%s)',
                $installment->plan->job->project->name,
                number_format($installment->amount / 100, 2, ',', '.').' €',
                $installment->due_on->format('d.m.Y'),
                $installment->overdue_reason?->label() ?? 'Frist überschritten',
            ))
            ->values()
            ->all();
    }

    /* Payments have no screen of their own; the project detail carries them. */
    public function url(): string
    {
        return $this->project() === null
            ? '/auftraege'
            : "/auftraege/{$this->project()->public_id}";
    }

    /** One project means its crew; several mean the studio picked the recipients already. */
    public function project(): ?Project
    {
        $projects = $this->installments->map(fn (PaymentInstallment $i) => $i->plan->job->project)->unique('id');

        return $projects->count() === 1 ? $projects->first() : null;
    }
}
