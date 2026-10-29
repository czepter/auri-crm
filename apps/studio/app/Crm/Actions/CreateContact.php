<?php

namespace App\Crm\Actions;

use App\Crm\Models\Contact;
use App\Models\User;
use App\Support\AuditLogger;

/** The only writer of new contacts. */
class CreateContact
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $portalOverrides
     */
    public function __invoke(array $attributes, array $portalOverrides = [], ?User $actor = null): Contact
    {
        $contact = Contact::create($attributes);

        // The model's own creating hook writes the studio greeting; these
        // depart from it, so they are merged onto what it left rather than
        // replacing it.
        if ($portalOverrides !== []) {
            $contact->forceFill(['portal_overrides' => array_merge($contact->portal_overrides ?? [], $portalOverrides)])->save();
        }

        if ($actor !== null) {
            AuditLogger::record(event: 'contact.created', actor: $actor, subject: $contact->public_id);
        }

        return $contact;
    }

    /**
     * @param  array{name: ?string, email?: ?string, phone?: ?string}  $attributes  used when $publicId is blank
     */
    public function pickOrResolve(?string $publicId, array $attributes): Contact
    {
        return filled($publicId)
            ? Contact::query()->where('public_id', $publicId)->firstOrFail()
            : $this->resolve($attributes);
    }

    /**
     * An address already on file is that person, not a second contact — the
     * partial unique index on (tenant_id, email) would refuse the insert
     * anyway. The stored name wins over the submitted one.
     *
     * @param  array{name: ?string, email?: ?string, phone?: ?string}  $attributes
     */
    public function resolve(array $attributes): Contact
    {
        $email = $attributes['email'] ?? null;
        $phone = $attributes['phone'] ?? null;

        $existing = filled($email) ? Contact::query()->where('email', $email)->first() : null;

        if ($existing === null) {
            return $this([
                'name' => $attributes['name'],
                'email' => $email,
                'phone' => $phone,
            ]);
        }

        // A phone number the studio does not have yet is worth keeping;
        // one it already has is not worth overwriting from a form.
        if (filled($phone) && blank($existing->phone)) {
            $existing->update(['phone' => $phone]);
        }

        return $existing;
    }
}
