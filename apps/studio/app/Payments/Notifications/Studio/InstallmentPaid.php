<?php

namespace App\Payments\Notifications\Studio;

use App\Crm\Models\ClientCase;
use App\Crm\Notifications\Studio\StudioEventNotification;
use App\Payments\Models\PaymentInstallment;

class InstallmentPaid extends StudioEventNotification
{
    public function __construct(private readonly PaymentInstallment $installment) {}

    public function title(): string
    {
        return 'Zahlung eingegangen';
    }

    public function body(): string
    {
        return $this->case()?->name ?? "Rechnung {$this->installment->invoice?->number}";
    }

    /* Payments have no screen of their own; the project detail carries them. */
    public function url(): string
    {
        $project = $this->case();

        return $project === null ? '/rechnungen' : "/auftraege/{$project->public_id}";
    }

    public function case(): ?ClientCase
    {
        return $this->installment->case();
    }
}
