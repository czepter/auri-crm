<?php

namespace App\Portal\Notifications\Studio;

use App\Crm\Models\ClientCase;
use App\Crm\Models\FormInstance;
use App\Crm\Notifications\Studio\StudioEventNotification;

class QuestionnaireSubmitted extends StudioEventNotification
{
    public function __construct(private readonly FormInstance $form) {}

    public function title(): string
    {
        return 'Fragebogen ausgefüllt';
    }

    public function body(): string
    {
        return "{$this->form->case->name} — {$this->form->name}";
    }

    public function url(): string
    {
        return "/auftraege/{$this->form->case->public_id}/frageboegen";
    }

    public function case(): ?ClientCase
    {
        return $this->form->case;
    }
}
