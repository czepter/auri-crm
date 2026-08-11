<?php

namespace App\Integration\Data;

use App\Crm\Models\Contact;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A contact as I-02's read endpoints hand it to an api-surface token.
 * public_id only — see HasPublicId — never the row's internal id.
 */
#[TypeScript]
class ContactReadData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public string $updatedAt,
    ) {}

    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: $contact->public_id,
            name: $contact->name,
            email: $contact->email,
            phone: $contact->phone,
            updatedAt: $contact->updated_at->toIso8601String(),
        );
    }
}
