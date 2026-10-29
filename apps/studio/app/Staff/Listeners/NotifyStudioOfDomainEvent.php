<?php

namespace App\Staff\Listeners;

use App\Contracts\Events\ContractSigned;
use App\Contracts\Notifications\Studio\ContractSigned as ContractSignedMail;
use App\Crm\Actions\NotifyStudio;
use App\Crm\Events\LeadCaptured;
use App\Crm\Notifications\Studio\InquiryReceived;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Notifications\Studio\InstallmentOverdue as InstallmentOverdueMail;
use App\Payments\Notifications\Studio\InstallmentPaid as InstallmentPaidMail;

/**
 * The staff-facing events this codebase dispatches. The other three the
 * profile screen lists — a quote accepted, a questionnaire handed
 * back, a client message — have no domain event of their own, so their
 * actions call NotifyStudio directly rather than growing an event nobody
 * else listens to.
 */
class NotifyStudioOfDomainEvent
{
    public function __construct(private readonly NotifyStudio $notify) {}

    public function handleLeadCaptured(LeadCaptured $event): void
    {
        ($this->notify)(new InquiryReceived($event->lead));
    }

    public function handleContractSigned(ContractSigned $event): void
    {
        ($this->notify)(new ContractSignedMail($event->contract));
    }

    public function handleInstallmentPaid(InstallmentPaid $event): void
    {
        ($this->notify)(new InstallmentPaidMail($event->installment));
    }

    /**
     * T-22: the first word about one installment, from the daily scan or
     * from Stripe. Every repeat after it is bundled by the scan itself —
     * see DetectOverdueInstallments::remindStudio().
     */
    public function handleInstallmentOverdue(InstallmentOverdue $event): void
    {
        ($this->notify)(new InstallmentOverdueMail(collect([$event->installment])));
    }
}
