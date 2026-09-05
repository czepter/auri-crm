<?php

namespace App\Notifications\Listeners;

use App\Contracts\Events\ContractSigned;
use App\Crm\Events\LeadCaptured;
use App\Notifications\Actions\NotifyStudio;
use App\Notifications\Studio;
use App\Payments\Events\InstallmentPaid;

/**
 * The three staff-facing events this codebase already dispatches. The other
 * three the profile screen lists — a quote accepted, a questionnaire handed
 * back, a client message — have no domain event of their own, so their
 * actions call NotifyStudio directly rather than growing an event nobody
 * else listens to.
 */
class NotifyStudioOfDomainEvent
{
    public function __construct(private readonly NotifyStudio $notify) {}

    public function handleLeadCaptured(LeadCaptured $event): void
    {
        ($this->notify)(new Studio\InquiryReceived($event->lead));
    }

    public function handleContractSigned(ContractSigned $event): void
    {
        ($this->notify)(new Studio\ContractSigned($event->contract));
    }

    public function handleInstallmentPaid(InstallmentPaid $event): void
    {
        ($this->notify)(new Studio\InstallmentPaid($event->installment));
    }
}
