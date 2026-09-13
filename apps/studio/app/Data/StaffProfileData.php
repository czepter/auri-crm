<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The typed shape of the signed-in staff user as the admin surface sees it.
 * `id` is the CUID2, never the primary key — that is the whole point of the
 * object existing rather than passing the model.
 */
#[TypeScript]
class StaffProfileData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $role,
        public bool $mfaConfirmed,
        // QuoteActions.vue's split button: whichever of download/copy_link/
        // send_by_mail this person reached for last, so it defaults there
        // again next time — see the `last_quote_action` migration.
        public ?string $lastQuoteAction,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->public_id,
            name: $user->name,
            email: $user->email,
            role: $user->role->value,
            mfaConfirmed: $user->hasConfirmedMfa(),
            lastQuoteAction: $user->last_quote_action,
        );
    }
}
