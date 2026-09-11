<?php

namespace App\Integration\Data;

use App\Crm\Models\Contact;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A contact as the single-project read hands it over: ContactReadData plus
 * the role this contact holds on that project.
 */
#[TypeScript]
class ProjectContactReadData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public string $role,
        public string $updatedAt,
    ) {}

    /** Requires the project's `contacts` relation loaded, for the pivot. */
    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: $contact->public_id,
            name: $contact->name,
            email: $contact->email,
            phone: $contact->phone,
            role: $contact->pivot->role,
            updatedAt: $contact->updated_at->toIso8601String(),
        );
    }
}
