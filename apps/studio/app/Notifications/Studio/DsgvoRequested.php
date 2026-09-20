<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Project;
use App\Portal\Models\DsgvoRequest;

class DsgvoRequested extends StudioEventNotification
{
    public function __construct(private readonly DsgvoRequest $request) {}

    public function title(): string
    {
        return 'Datenschutz-Anfrage';
    }

    public function body(): string
    {
        return sprintf('%s · %s · Antwort bis %s', $this->request->contact->name, $this->request->label(), $this->request->dueOn()->format('d.m.Y'));
    }

    /** @return list<string> */
    public function lines(): array
    {
        return [sprintf(
            '%s hat im Portal %s angefragt. Die Frist zur Antwort endet am %s.',
            $this->request->contact->name,
            $this->request->kind === DsgvoRequest::EXPORT ? 'eine Datenkopie' : 'die Löschung',
            $this->request->dueOn()->format('d.m.Y'),
        )];
    }

    public function url(): string
    {
        return "/kontakte/{$this->request->contact->public_id}";
    }

    public function project(): ?Project
    {
        return null;
    }
}
