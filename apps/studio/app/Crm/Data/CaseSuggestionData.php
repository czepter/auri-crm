<?php

namespace App\Crm\Data;

use App\Crm\Models\ClientCase;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CaseSuggestionData extends Data
{
    /** @param  array{downloads: bool|null, lists: bool|null, comments: bool|null}  $galleryDefaults */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $contactName,
        public bool $isBooked,
        public array $galleryDefaults,
    ) {}

    public static function fromModel(ClientCase $project): self
    {
        $contact = $project->primaryContact();
        $overrides = $contact?->portal_overrides ?? [];

        return new self(
            id: $project->public_id,
            name: $project->name,
            contactName: $contact?->name,
            isBooked: $project->isBooked(),
            // Reisen mit dem Vorschlag, weil das Anlegen-Formular die
            // Geburtskette clientseitig zu Ende rechnet — ohne sie bräuchte
            // die Projektwahl einen zweiten Weg zum Server.
            galleryDefaults: [
                'downloads' => $overrides['gallery_downloads'] ?? null,
                'lists' => $overrides['gallery_lists'] ?? null,
                'comments' => $overrides['gallery_comments'] ?? null,
            ],
        );
    }
}
