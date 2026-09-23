<?php

namespace App\Crm\Data;

use App\Crm\Models\Contact;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContactSuggestionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public int $projectCount,
    ) {}

    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: $contact->public_id,
            name: $contact->name,
            email: $contact->email,
            phone: $contact->phone,
            // cases_count comes from withCount() on the query. Never
            // lazy-loaded here, or the typeahead becomes an N+1 on every
            // keystroke.
            projectCount: (int) ($contact->cases_count ?? 0),
        );
    }
}
