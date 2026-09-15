<?php

namespace App\Crm\Listeners;

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Events\ProjectBooked;

/**
 * A listener rather than a call inside BookProject, so every trigger that can
 * book a project gets the tasks without remembering to ask for them — P-05b's
 * contract path fires the same event as the manual button.
 *
 * Synchronous: the tasks are part of what booking means, and the studio
 * expects to see them on the screen it lands on after pressing the button.
 */
class ApplyTemplatesOnBooking
{
    public function __construct(private readonly ApplyTaskTemplates $apply) {}

    public function handle(ProjectBooked $event): void
    {
        ($this->apply)($event->project);
    }
}
