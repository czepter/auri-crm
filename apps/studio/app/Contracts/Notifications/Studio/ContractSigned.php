<?php

namespace App\Contracts\Notifications\Studio;

use App\Contracts\Models\Contract;
use App\Crm\Models\ClientCase;
use App\Crm\Notifications\Studio\StudioEventNotification;

class ContractSigned extends StudioEventNotification
{
    public function __construct(private readonly Contract $contract) {}

    public function title(): string
    {
        return 'Vertrag unterschrieben';
    }

    public function body(): string
    {
        return $this->contract->case->name;
    }

    public function url(): string
    {
        return "/auftraege/{$this->contract->case->public_id}/vertrag";
    }

    public function case(): ?ClientCase
    {
        return $this->contract->case;
    }
}
