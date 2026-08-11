<?php

namespace App\Integration\Listeners;

use App\Contracts\Events\ContractSigned;
use App\Crm\Events\ProjectBooked;
use App\Notifications\DomainEventOccurred;
use App\Notifications\NotificationService;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Events\InstallmentPaid;

/**
 * I-04: the only place any of these four domain events is wrapped for
 * NotificationService — see each event's own docblock, which already names
 * this story as their eventual listener. Registered by hand in
 * AppServiceProvider (App\Payments\Events\InstallmentPaid already has two
 * other listeners registered the same way).
 *
 * Every payload carries public_id only, never a bare integer — the same
 * rule G-01's API responses follow, because this one leaves the server in
 * an HTTP body exactly like an API response does.
 */
class DispatchWebhookNotifications
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handleProjectBooked(ProjectBooked $event): void
    {
        $this->notifications->send($event->project, new DomainEventOccurred('ProjectBooked', [
            'project' => $event->project->public_id,
            'job' => $event->job->public_id,
        ]));
    }

    public function handleContractSigned(ContractSigned $event): void
    {
        $this->notifications->send($event->contract, new DomainEventOccurred('ContractSigned', [
            'contract' => $event->contract->public_id,
            'signature' => $event->signature->public_id,
        ]));
    }

    public function handleInstallmentPaid(InstallmentPaid $event): void
    {
        $this->notifications->send($event->installment, new DomainEventOccurred('InstallmentPaid', [
            'installment' => $event->installment->public_id,
        ]));
    }

    public function handleInstallmentOverdue(InstallmentOverdue $event): void
    {
        $this->notifications->send($event->installment, new DomainEventOccurred('InstallmentOverdue', [
            'installment' => $event->installment->public_id,
        ]));
    }
}
