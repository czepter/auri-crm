<?php

namespace App\Crm\Data;

use App\Crm\Models\Contact;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A contact as the case and the project screen list them: who, in which role,
 * and the two ways to reach them. The phone number is the reason the screen is
 * open, so it travels with the name rather than a click away.
 */
#[TypeScript]
class CasePersonData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public ?string $email,
        public ?string $phone,
    ) {}

    public static function fromContact(Contact $contact): self
    {
        return new self(
            id: $contact->public_id,
            name: $contact->name,
            role: $contact->pivot->role,
            email: $contact->email,
            phone: $contact->phone,
        );
    }
}
