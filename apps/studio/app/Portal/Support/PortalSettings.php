<?php

namespace App\Portal\Support;

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\StudioClosure;
use App\Models\StudioSettings;
use App\Models\User;

/**
 * P-09 (#228). What the portal does, for this client.
 *
 * Two levels, in one place: the studio's default, and whatever this contact
 * overrides. Per CONTACT and not per project — a venue with forty dates a
 * year is set up once, not forty times — and every override is three-valued:
 * absent means inherit, which is what a nullable boolean column would have
 * blurred into "off". That is the whole reason contacts.portal_overrides is
 * one JSONB blob rather than a dozen nullable columns.
 *
 * Not everything is overridable and that is deliberate. Maintenance, the
 * legal links and the footer's contact line stay
 * studio-wide: they are the studio speaking about itself. Access revocation
 * stays per contact (contacts.portal_access_revoked_at) and the visibility
 * flag itself per booking (cases.portal_visible) — both A-04's, only
 * displayed here. 'portal_visible' in OVERRIDABLE is not that flag: it is
 * the value a booking captured for this contact is born with.
 */
class PortalSettings
{
    /** Messaging is three states, not a switch — see P-04 (#223). */
    public const MESSAGING_OFF = 'off';

    public const MESSAGING_BOOKING = 'booking';

    public const MESSAGING_ANY = 'any';

    /** @var list<string> */
    public const OVERRIDABLE = [
        'welcome_inquiry', 'welcome_active', 'welcome_done',
        'accept_quotes', 'sign_contracts', 'payment_status', 'messaging_mode',
        'gallery_downloads', 'gallery_lists', 'gallery_comments', 'portal_visible', 'offers',
    ];

    public function acceptQuotesEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'accept_quotes', (bool) StudioSettings::current()->portal_accept_quotes_enabled);
    }

    public function signContractsEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'sign_contracts', (bool) StudioSettings::current()->portal_sign_contracts_enabled);
    }

    public function paymentStatusEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'payment_status', (bool) StudioSettings::current()->portal_payment_status_enabled);
    }

    /**
     * Ob dieser Kunde „Weitere Leistungen" sieht. Eigener Schalter und nicht
     * an accept_quotes gehängt: ein Kunde mitten in einer Reklamation soll
     * nichts angeboten bekommen, darf aber weiter annehmen, was er schon
     * bekommen hat.
     */
    public function offersEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'offers', (bool) StudioSettings::current()->portal_offers_enabled);
    }

    public function messagingMode(?Contact $contact = null): string
    {
        return (string) $this->resolve($contact, 'messaging_mode', (string) StudioSettings::current()->portal_messaging_mode);
    }

    /** Whether the mailbox exists at all for this client. */
    public function messagingEnabled(?Contact $contact = null): bool
    {
        return $this->messagingMode($contact) !== self::MESSAGING_OFF;
    }

    /** Whether a thread may be started that names no booking (P-04). */
    public function generalThreadsAllowed(?Contact $contact = null): bool
    {
        return $this->messagingMode($contact) === self::MESSAGING_ANY;
    }

    public function galleryDownloadsEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'gallery_downloads', (bool) StudioSettings::current()->gallery_default_downloads_enabled);
    }

    public function galleryListsEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'gallery_lists', (bool) StudioSettings::current()->gallery_default_lists_enabled);
    }

    public function galleryCommentsEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'gallery_comments', (bool) StudioSettings::current()->gallery_default_comments_enabled);
    }

    /**
     * The greeting for the state the client is actually in. Three rooms:
     * nothing booked yet, a booking under way, and everything delivered. A
     * sentence written for one reads as a mistake in the others — and the
     * stranger with an inquiry is not the client whose pictures are done.
     */
    public function welcomeText(?Contact $contact, string $state): ?string
    {
        $own = $this->overrides($contact)["welcome_{$state}"] ?? null;

        if ($own !== null) {
            return $own;
        }

        return match ($state) {
            'inquiry' => StudioSettings::current()->portal_welcome_inquiry,
            'done' => StudioSettings::current()->portal_welcome_done,
            default => StudioSettings::current()->portal_welcome_active,
        };
    }

    /**
     * Whether a booking captured for this contact starts out visible in the
     * portal. A client the studio already shows its work to should not be
     * switched on again per booking — a venue with forty dates a year least
     * of all. Off unless this contact says otherwise: a raw inquiry from a
     * stranger stays internal (vision §3.1).
     */
    public function projectsVisibleByDefault(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'portal_visible', false);
    }

    /**
     * Wer auf der Kontaktkarte steht, und womit sie erreichbar ist.
     *
     * Der Name fällt auf die Marke zurück: ein Ein-Personen-Studio trägt
     * beides, und eine Karte ohne Namen liest sich als Ladefehler (siehe
     * portal/lib/studio's `reachable`). Die Nummer fällt auf die des
     * Inhabers zurück, damit ein Studio, das nie in den Einstellungen war,
     * erreichbar bleibt. Die Adresse nicht: ein Login ist ein Zugangsdatum
     * und nichts, was ein Kundenbildschirm von sich aus zeigen darf.
     *
     * Eine laufende oder bevorstehende Schließzeit mit Text steht als
     * `absence` und ersetzt die Antwortzeit: solange das Studio zu ist,
     * stimmt sie nicht.
     */
    public function contactLine(): array
    {
        return [
            'name' => StudioSettings::current()->portal_contact_name ?? StudioSettings::current()->display_name,
            'avatarUrl' => StudioSettings::current()->portal_contact_avatar_path !== null ? '/branding/kontakt' : null,
            'phone' => StudioSettings::current()->portal_contact_phone ?? $this->ownerPhone(),
            'email' => StudioSettings::current()->portal_contact_email,
            'responseTime' => $this->closure() === null ? StudioSettings::current()->portal_contact_response_time : null,
            'absence' => $this->absence(),
        ];
    }

    private function closure(): ?StudioClosure
    {
        return StudioClosure::query()
            ->whereNotNull('message')
            ->whereDate('ends_on', '>=', today())
            ->whereDate('starts_on', '<=', today()->addDays(7))
            ->orderBy('starts_on')
            ->first();
    }

    /** @return array{range: string, message: string}|null */
    private function absence(): ?array
    {
        $closure = $this->closure();

        if ($closure === null) {
            return null;
        }

        return [
            'range' => $closure->starts_on->isSameDay($closure->ends_on)
                ? $closure->starts_on->format('d.m.')
                : $closure->starts_on->format('d.m.').' bis '.$closure->ends_on->format('d.m.'),
            'message' => $closure->message,
        ];
    }

    private function ownerPhone(): ?string
    {
        return User::query()->where('role', StaffRole::Admin)->value('phone');
    }

    /**
     * What the studio's own screen shows: the default, and per key whether
     * this contact departs from it.
     *
     * @return array<string, mixed>
     */
    public function overrides(?Contact $contact): array
    {
        return $contact?->portal_overrides ?? [];
    }

    private function resolve(?Contact $contact, string $key, mixed $default): mixed
    {
        return $contact?->portalOverride($key, $default) ?? $default;
    }
}
