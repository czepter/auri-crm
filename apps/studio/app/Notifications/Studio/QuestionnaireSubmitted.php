<?php

namespace App\Notifications\Studio;

use App\Crm\Models\FormInstance;
use App\Crm\Models\Project;

class QuestionnaireSubmitted extends StudioEventNotification
{
    public function __construct(private readonly FormInstance $form) {}

    public function title(): string
    {
        return 'Fragebogen ausgefüllt';
    }

    public function body(): string
    {
        return "{$this->form->project->name} — {$this->form->name}";
    }

    public function url(): string
    {
        return "/auftraege/{$this->form->project->public_id}/frageboegen";
    }

    public function project(): ?Project
    {
        return $this->form->project;
    }
}
