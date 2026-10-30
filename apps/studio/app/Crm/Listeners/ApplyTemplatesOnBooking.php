<?php

namespace App\Crm\Listeners;

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Events\CaseBooked;

/**
 * A listener rather than a call inside BookCase, so every trigger that can
 * book a project gets the tasks without remembering to ask for them.
 *
 * Synchronous: the tasks are part of what booking means, and the studio
 * expects to see them on the screen it lands on after pressing the button.
 */
class ApplyTemplatesOnBooking
{
    public function __construct(private readonly ApplyTaskTemplates $apply) {}

    public function handle(CaseBooked $event): void
    {
        ($this->apply)($event->project);
    }
}
