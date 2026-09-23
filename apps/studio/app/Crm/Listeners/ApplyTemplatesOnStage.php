<?php

namespace App\Crm\Listeners;

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Enums\StagePipeline;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Events\ShootStatusChanged;

/**
 * A stage the project reaches owes what the workflow says it owes.
 *
 * Booking keeps its own listener (ApplyTemplatesOnBooking): a lead that jumps
 * straight to "Gebucht" and the Buchen button that books the project without the
 * board ever moving are two paths to the same stage, and only one of them changes a
 * lead stage.
 */
class ApplyTemplatesOnStage
{
    public function __construct(private readonly ApplyTaskTemplates $apply) {}

    public function handleLeadStage(LeadStageChanged $event): void
    {
        ($this->apply)->forStage($event->lead->case, StagePipeline::Lead, $event->to->key);
    }

    public function handleShootStatus(ShootStatusChanged $event): void
    {
        ($this->apply)->forStage($event->shoot->case, StagePipeline::Project, $event->to->key);
    }
}
