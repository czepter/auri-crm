<?php

namespace App\Notifications\Studio;

use App\Contracts\Models\Contract;
use App\Crm\Models\Project;

class ContractSigned extends StudioEventNotification
{
    public function __construct(private readonly Contract $contract) {}

    public function title(): string
    {
        return 'Vertrag unterschrieben';
    }

    public function body(): string
    {
        return $this->contract->project->name;
    }

    public function url(): string
    {
        return "/auftraege/{$this->contract->project->public_id}/vertrag";
    }

    public function project(): ?Project
    {
        return $this->contract->project;
    }
}
