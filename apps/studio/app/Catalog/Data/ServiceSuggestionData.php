<?php

namespace App\Catalog\Data;

use App\Catalog\Models\ServiceSuggestion;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ServiceSuggestionData extends Data
{
    public function __construct(
        public string $id,
        public string $contact,
        public string $package,
        public string $packageName,
        public ?string $note,
        public ?string $author,
        public string $suggestedOn,
        public bool $live,
    ) {}

    public static function fromModel(ServiceSuggestion $suggestion): self
    {
        return new self(
            id: $suggestion->public_id,
            contact: $suggestion->contact->public_id,
            package: $suggestion->package->public_id,
            packageName: $suggestion->package->name,
            note: $suggestion->note,
            author: $suggestion->suggestedBy?->name,
            suggestedOn: $suggestion->created_at->toDateString(),
            live: $suggestion->package->portal_offered,
        );
    }
}
