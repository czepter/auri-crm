<?php

namespace App\Portal\Support;

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\User;
use App\Settings\Support\StudioSettingsResolver;

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
 * legal links, the DSGVO recipient and the footer's contact line stay
 * studio-wide: they are the studio speaking about itself. Access revocation
 * stays per contact (contacts.portal_access_revoked_at) and the visibility
 * flag itself per booking (projects.portal_visible) — both A-04's, only
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
        'welcome', 'accept_quotes', 'payment_status', 'messaging_mode',
        'gallery_downloads', 'gallery_lists', 'gallery_comments', 'portal_visible', 'offers',
    ];

    public function __construct(private readonly StudioSettingsResolver $studio) {}

    public function acceptQuotesEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'accept_quotes', $this->studio->acceptQuotesEnabled());
    }

    public function paymentStatusEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'payment_status', $this->studio->paymentStatusEnabled());
    }

    /**
     * Ob dieser Kunde „Weitere Leistungen" sieht. Eigener Schalter und nicht
     * an accept_quotes gehängt: ein Kunde mitten in einer Reklamation soll
     * nichts angeboten bekommen, darf aber weiter annehmen, was er schon
     * bekommen hat.
     */
    public function offersEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'offers', $this->studio->offersEnabled());
    }

    public function messagingMode(?Contact $contact = null): string
    {
        return (string) $this->resolve($contact, 'messaging_mode', $this->studio->messagingMode());
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
        return (bool) $this->resolve($contact, 'gallery_downloads', $this->studio->galleryDownloadsEnabled());
    }

    public function galleryListsEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'gallery_lists', $this->studio->galleryListsEnabled());
    }

    public function galleryCommentsEnabled(?Contact $contact = null): bool
    {
        return (bool) $this->resolve($contact, 'gallery_comments', $this->studio->galleryCommentsEnabled());
    }

    /**
     * The greeting for the state the client is actually in. Three rooms:
     * before the first booking, with one, and with a stock of them. A
     * sentence written for one reads as a mistake in the others.
     */
    public function welcomeText(?Contact $contact, string $state): ?string
    {
        $own = $this->overrides($contact)['welcome'] ?? null;

        if ($own !== null) {
            return $own;
        }

        return match ($state) {
            'inquiry' => $this->studio->portalWelcomeInquiry(),
            'many' => $this->studio->portalWelcomeMany(),
            default => $this->studio->portalWelcomeText(),
        } ?? $this->studio->portalWelcomeText();
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
     * The footer's contact line, which had no source before P-09.
     *
     * The number falls back to the owner's own, so a studio that never
     * opened /einstellungen/portal is still reachable. The address does not:
     * an owner's login is a credential, not something a client screen may
     * start showing on its own.
     */
    public function contactLine(): array
    {
        return [
            'phone' => $this->studio->portalContactPhone() ?? $this->ownerPhone(),
            'email' => $this->studio->portalContactEmail(),
            'responseTime' => $this->studio->portalContactResponseTime(),
        ];
    }

    private function ownerPhone(): ?string
    {
        return User::query()->where('role', StaffRole::Owner)->value('phone');
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
        return $this->overrides($contact)[$key] ?? $default;
    }
}
