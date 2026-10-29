<?php

namespace App\Portal\Listeners;

use App\Crm\Events\CaseBooked;
use App\Portal\Support\PortalSettings;

/**
 * Vision §3.1. Only an explicit `false` override holds the project back:
 * projectsVisibleByDefault() answers false for a contact with no override,
 * which is the inquiry's default and not a refusal.
 */
class RevealCaseOnBooking
{
    public function __construct(private readonly PortalSettings $portal) {}

    public function handle(CaseBooked $event): void
    {
        $project = $event->project;

        if (($this->portal->overrides($project->primaryContact())['portal_visible'] ?? true) === false) {
            return;
        }

        $project->update(['portal_visible' => true]);
    }
}
