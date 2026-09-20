<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Project;
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
        return $this->project()?->name ?? "Rechnung {$this->installment->invoice?->number}";
    }

    /* Payments have no screen of their own; the project detail carries them. */
    public function url(): string
    {
        $project = $this->project();

        return $project === null ? '/rechnungen' : "/auftraege/{$project->public_id}";
    }

    public function project(): ?Project
    {
        return $this->installment->project();
    }
}
