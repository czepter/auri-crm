<?php

namespace App\Settings\Support;

use App\Models\User;
use App\Settings\Enums\FontPair;
use App\Settings\Models\StudioSettings;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The tenant-scoped config service Vision §12 asked for and nothing built
 * until T-01. Injected into whatever needs a setting — never a
 * `Settings::get('key')` facade reaching into a global — so a caller's
 * dependency on the settings store shows up in its own constructor.
 *
 * Always reads and invalidates through the `redis` store by name, never
 * config('cache.default') (`array` in tests, `database` otherwise — see
 * config/cache.php and phpunit.xml). M-00 put a Horizon worker beside the
 * web container specifically so a value the web request just changed is
 * never rendered stale into a document the worker is building. An array or
 * file cache only ever invalidates inside the one process that wrote it;
 * Redis is the one backend both processes actually share, with no deploy
 * and no cache-clear command in between.
 */
class StudioSettingsResolver
{
    private const CACHE_KEY_PREFIX = 'studio_settings:';

    private const TTL = 3600;

    public function displayName(): ?string
    {
        return $this->row()->display_name;
    }

    public function publicId(): string
    {
        return $this->row()->public_id;
    }

    /** 'standard' or 'small_business' — see the studio_settings table's check constraint for the domain. */
    public function vatMode(): string
    {
        return $this->row()->vat_mode;
    }

    /**
     * The standard-mode rate, in basis points (1900 = 19.00%) — never zero
     * out this value on a small_business switch, or switching back to
     * standard has nothing to restore. Q-04 wants effectiveVatRate()
     * instead of this for anything it actually prints.
     */
    public function vatRate(): int
    {
        return $this->row()->vat_rate;
    }

    public function vatNote(): ?string
    {
        return $this->row()->vat_note;
    }

    /**
     * T-09: the length ShootCalendar gives a shoot whose end_time is unset,
     * replacing the hardcoded addHours(2) that used to sit in that class
     * directly. Minutes, not hours — a shorter session needs finer
     * granularity than that constant offered.
     */
    public function defaultShootDurationMinutes(): int
    {
        return $this->row()->default_shoot_duration_minutes;
    }

    /**
     * What a document should actually use — zero under small_business
     * regardless of what vat_rate holds, so a mode switch never leaves a
     * stale 19% behind. This is T-03's whole point: Q-04 calls this, not
     * vatRate(), the moment it prints a total.
     */
    public function effectiveVatRate(): int
    {
        return $this->vatMode() === 'small_business' ? 0 : $this->vatRate();
    }

    /**
     * A quote's validity in days, used the moment a quote is created with no
     * explicit valid_until and no validity of its own to carry forward — see
     * App\Quotes\Http\Controllers\QuoteController::store(). Package carries
     * no default_validity_days column today (App\Catalog\Models\Package), so
     * this is the only fallback there is, not a second one behind a
     * package's own value.
     */
    public function quoteValidityDays(): int
    {
        return $this->row()->quote_validity_days;
    }

    /** Zahlungsziel in days — T-07's overdue scan reads this once it ships. */
    public function paymentTermDays(): int
    {
        return $this->row()->payment_term_days;
    }

    /**
     * Basis points (0-10000), same convention as vatRate() above — pre-fills
     * a new PaymentPlan's deposit installment, never re-read once one exists.
     */
    public function depositPercentage(): int
    {
        return $this->row()->deposit_percentage;
    }

    /** Whether a rendered contract's `settings.widerrufText` merge field carries any text at all. */
    public function widerrufIncluded(): bool
    {
        return $this->row()->widerruf_included;
    }

    public function widerrufText(): ?string
    {
        return $this->row()->widerruf_text;
    }

    /**
     * T-12: the PDF shell App\Documents\Support\DocumentRenderer wraps a
     * Contract or Quote's resolved body in, above it. Null renders as no
     * letterhead at all — the same "unset is a valid, working state"
     * convention widerrufText() above already carries.
     */
    public function documentLetterheadHtml(): ?string
    {
        return $this->row()->document_letterhead_html;
    }

    /** The same shell's footer, below the resolved body. */
    public function documentFooterHtml(): ?string
    {
        return $this->row()->document_footer_html;
    }

    /**
     * IANA identifier (e.g. "Europe/Vienna") — the tenant value once T-13's
     * Allgemein pane has set one, config('app.timezone') as the fallback
     * until then. The environment stays the fallback, never the source: a
     * studio that has not opened this pane yet sees exactly what it saw
     * before this story existed.
     */
    public function timezone(): string
    {
        return $this->row()->timezone ?? config('app.timezone');
    }

    /**
     * Carbon's formatting locale for the three DACH variants T-02's own
     * country select already offers (de_DE/de_AT/de_CH) — never
     * config('app.locale')'s translation locale, which stays 'de' and
     * env-independent on purpose (see config/app.php). Same fallback shape
     * as timezone() above: the tenant value once set, the environment
     * otherwise.
     */
    public function locale(): string
    {
        return $this->row()->locale ?? config('app.locale');
    }

    /**
     * ISO weekday integers (1 Monday .. 7 Sunday) the studio works —
     * TaskTemplate::dueFor() rolls a due date forward off any day not in
     * this set. Guards an empty array (should never reach the database
     * past StudioSettingsController's validation, but dueFor() loops on
     * this set, and an empty one loops forever) by treating it the same as
     * "every day", rather than letting a corrupt row hang a request.
     *
     * @return list<int>
     */
    public function workingDays(): array
    {
        $days = $this->row()->working_days;

        return empty($days) ? [1, 2, 3, 4, 5, 6, 7] : $days;
    }

    /**
     * The details this studio keeps typing under "Sonstige" at capture —
     * offered as suggestions on the three fields, never as a closed list.
     *
     * @return list<string>
     */
    public function leadPresets(string $kind): array
    {
        return $this->row()->{"lead_{$kind}_presets"} ?? [];
    }

    /**
     * The stored path of the logo — the ONE file both RenderContractJob's
     * `studio.logoPath` merge field and a future portal-branding read are
     * meant to resolve against (T-02's acceptance criterion). Never a second
     * upload for a second context: whoever needs the logo reads it through
     * here.
     */
    public function logoPath(): ?string
    {
        return $this->row()->logo_path;
    }

    /**
     * T-02's legal-entity, brand and bank columns, keyed by column name —
     * the one place outside this class permitted to see them, and only as
     * plain values. The Studio-Profil edit screen and RenderContractJob's
     * `studio.*` merge fields (docs/documents.md) both read through this
     * rather than through row() itself, which stays private.
     *
     * @return array<string, mixed>
     */
    /**
     * #234: the display pair this studio's portal and gallery are written in.
     * Null is Hanken, the product's own default.
     */
    public function fontPair(): FontPair
    {
        return FontPair::tryFrom((string) $this->row()->font_pair) ?? FontPair::Hanken;
    }

    public function attributes(): array
    {
        return Arr::only($this->row()->getAttributes(), [
            'display_name',
            'legal_name', 'legal_form', 'street', 'postcode', 'city', 'country',
            'tax_number', 'vat_id', 'register_number', 'register_court', 'representative',
            'website', 'accent_color', 'logo_path', 'font_pair',
            'bank_account_holder', 'bank_iban', 'bank_bic',
        ]);
    }

    /**
     * The logo is stored once (T-02's acceptance criterion) — a re-upload
     * replaces the same tenant's file and removes whatever it replaces,
     * rather than accumulating a second one under a second name. Uses the
     * `local` disk the exact way RenderContractJob stores a rendered
     * contract (Storage::disk('local'), see docs/documents.md), not a
     * second filesystem convention invented for branding alone.
     */
    public function setLogo(UploadedFile $file, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();
        $old = $row->logo_path;

        $path = $file->storeAs("branding/{$row->tenant_id}", 'logo.'.$file->extension(), 'local');

        if ($old !== null && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $row->update(['logo_path' => $path]);

        Cache::store('redis')->forget($this->cacheKey());

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['key' => 'logo_path', 'old' => $old, 'new' => $path],
            subject: $row->public_id,
        );
    }

    /**
     * Writes one column, busts the cache both processes read through, and
     * leaves an audit row naming the actor and both values — T-01's issue,
     * "every write goes through the existing AuditLog". Reads the row fresh
     * off the database rather than through row()'s cache, so a stale cached
     * copy can never write a wrong "old" value into the audit trail.
     *
     * A no-op write (old === new) leaves neither an audit row nor a cache
     * bust: "every change" in the acceptance criteria, not every request.
     */
    public function set(string $key, mixed $value, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();
        $old = $row->{$key};

        if ($old === $value) {
            return;
        }

        $row->update([$key => $value]);

        Cache::store('redis')->forget($this->cacheKey());

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['key' => $key, 'old' => $old, 'new' => $value],
            subject: $row->public_id,
        );
    }

    // --- T-07: Stripe as an optional capability, and the provider-independent defaults ---

    public function stripeEnabled(): bool
    {
        return $this->row()->payments_stripe_enabled;
    }

    /** 'test' or 'live' — which of the two key pairs setPaymentSettings() below writes is active right now. */
    public function stripeMode(): string
    {
        return $this->row()->payments_stripe_mode;
    }

    /**
     * Whether a key pair exists for the given mode — the settings screen's
     * own "already configured" indicator, so the write-only key fields can
     * say that much without ever echoing a value back. Never used to decide
     * whether Stripe may actually be called; that is activeStripeSecretKey()'s
     * job, for M-04/M-05.
     */
    public function hasStripeKeys(string $mode): bool
    {
        $row = $this->row();

        return $mode === 'live'
            ? $row->payments_stripe_live_publishable_key !== null && $row->payments_stripe_live_secret_key !== null
            : $row->payments_stripe_test_publishable_key !== null && $row->payments_stripe_test_secret_key !== null;
    }

    /**
     * The secret key for whichever mode is active — decrypted by the
     * model's own `encrypted` cast, the one seam M-04/M-05 are expected to
     * call rather than reading the column themselves. Nothing in this
     * story ever prints this value anywhere; see StudioSettingsAdminTest's
     * "the Stripe secret key never appears in any response body" coverage.
     */
    public function activeStripeSecretKey(): ?string
    {
        $row = $this->row();

        return $this->stripeMode() === 'live'
            ? $row->payments_stripe_live_secret_key
            : $row->payments_stripe_test_secret_key;
    }

    public function activeStripePublishableKey(): ?string
    {
        $row = $this->row();

        return $this->stripeMode() === 'live'
            ? $row->payments_stripe_live_publishable_key
            : $row->payments_stripe_test_publishable_key;
    }

    // Deposit percentage and Zahlungsziel — the other two provider-independent
    // defaults T-07's own issue named — are already T-05's depositPercentage()
    // and paymentTermDays() above (studio_settings.deposit_percentage and
    // .payment_term_days, the Dokumente pane): T-05 landed first, so T-07
    // reads those two through T-05's columns rather than declaring a second,
    // redundant pair. remindersEnabled() below is the one default T-05 did
    // not cover.

    public function remindersEnabled(): bool
    {
        return $this->row()->payments_reminders_enabled;
    }

    /**
     * T-07's own pane, saved in one submit like Studio-Profil (T-02) and
     * unlike Zahlungsdaten & USt's own endpoint — nothing here needs the
     * "own fieldset, own button" separation those two have, because every
     * field on this pane lives or dies with the same "Zahlungen" save.
     *
     * The four key columns are written only when a NEW, non-empty value is
     * given — the write-only form (never echoing a stored key back) has no
     * other way to say "leave this pair alone", and an empty string arriving
     * from an untouched field must never null out a key that already works.
     * Audited by column name only: a key's value never rides along into
     * AuditLog, the same "never leaves the cast that decrypts it" rule that
     * keeps it out of every Inertia prop and API response.
     *
     * @param  array{
     *     payments_stripe_enabled: bool,
     *     payments_stripe_mode: string,
     *     payments_reminders_enabled: bool,
     *     payments_stripe_test_publishable_key?: ?string,
     *     payments_stripe_test_secret_key?: ?string,
     *     payments_stripe_live_publishable_key?: ?string,
     *     payments_stripe_live_secret_key?: ?string,
     * }  $attributes
     */
    public function setPaymentSettings(array $attributes, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();

        $plainKeys = [
            'payments_stripe_enabled', 'payments_stripe_mode', 'payments_reminders_enabled',
        ];
        $keyColumns = [
            'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
            'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
        ];

        $updates = [];
        $changedNames = [];
        $plainChanges = [];

        foreach ($plainKeys as $key) {
            if ($attributes[$key] !== $row->{$key}) {
                $plainChanges[$key] = [$row->{$key}, $attributes[$key]];
                $updates[$key] = $attributes[$key];
                $changedNames[] = $key;
            }
        }

        foreach ($keyColumns as $column) {
            if (! empty($attributes[$column] ?? null)) {
                $updates[$column] = $attributes[$column];
                $changedNames[] = $column;
            }
        }

        if ($updates === []) {
            return;
        }

        $row->update($updates);

        Cache::store('redis')->forget($this->cacheKey());

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['keys' => $changedNames, 'changes' => $plainChanges],
            subject: $row->public_id,
        );
    }

    // --- T-06: mail identity — who a tenant's outgoing mail leaves under ---

    /**
     * config('mail.from.name')'s per-tenant replacement — the same fallback
     * shape as timezone()/locale() above: the tenant value once T-06's
     * E-Mail pane has set one, the environment default otherwise.
     */
    public function mailFromName(): string
    {
        return $this->row()->mail_from_name ?: config('mail.from.name');
    }

    public function mailFromAddress(): string
    {
        return $this->row()->mail_from_address ?: config('mail.from.address');
    }

    /** Null when unset — a reply then goes to mailFromAddress(), the mailer's own default. */
    public function mailReplyTo(): ?string
    {
        return $this->row()->mail_reply_to;
    }

    /** Appended as its own line on every outgoing notification mail this story wires up. */
    public function mailSignature(): ?string
    {
        return $this->row()->mail_signature;
    }

    /** Null leaves outgoing mail unBCC'd — the studio's own inbox is opt-in. */
    public function mailBcc(): ?string
    {
        return $this->row()->mail_bcc;
    }

    /**
     * E-12: the secret every inbound address is minted from. Null until the
     * studio has one, which is also what makes ingest fall closed into
     * triage on a deployment that never provisioned an inbound domain.
     *
     * Never leaves the server. It is not in attributes(), so no Inertia prop
     * or API response can carry it by accident — the addresses derived from
     * it are what the studio sees.
     */
    public function inboundSecret(): ?string
    {
        return $this->row()->inbound_secret;
    }

    // --- T-08: the portal's own settings — a maintenance closure, home
    // branding text, two external legal links, and four client capability
    // switches. See the migration's own doc comment for why each defaults
    // where it does.

    /** Whole-portal temporary closure — never a substitute for A-04's own hide/revoke actions. */
    public function maintenanceEnabled(): bool
    {
        return $this->row()->portal_maintenance_enabled;
    }

    /** Free text shown instead of the portal while closed; EnsurePortalMaintenanceClosure falls back to a default if blank. */
    public function maintenanceMessage(): ?string
    {
        return $this->row()->portal_maintenance_message;
    }

    /** P-01's room: exactly one booking. The column T-08 shipped, unrenamed so nothing a studio wrote is lost. */
    public function portalWelcomeText(): ?string
    {
        return $this->row()->portal_welcome_text;
    }

    /** External link — no local Datenschutzerklärung page exists or is planned here. */
    public function legalDatenschutzUrl(): ?string
    {
        return $this->row()->portal_legal_datenschutz_url;
    }

    /** External link — no local AGB page exists or is planned here. */
    public function legalAgbUrl(): ?string
    {
        return $this->row()->portal_legal_agb_url;
    }

    /** Gates Q-07's accept action. */
    public function acceptQuotesEnabled(): bool
    {
        return $this->row()->portal_accept_quotes_enabled;
    }

    /** Gates M-02's sign action — the contract itself stays viewable either way. */
    public function signContractsEnabled(): bool
    {
        return $this->row()->portal_sign_contracts_enabled;
    }

    /** Gates T-07's payment screen in full. */
    public function paymentStatusEnabled(): bool
    {
        return $this->row()->portal_payment_status_enabled;
    }

    /**
     * P-09 (#228): three states, not a switch. `off`, `booking` (threads
     * must name one), `any` (P-04's booking-less threads too). Read through
     * App\Portal\Support\PortalSettings, which layers a contact's own
     * override over it.
     */
    public function messagingMode(): string
    {
        return (string) $this->row()->portal_messaging_mode;
    }

    /** P-09: what a new gallery starts as, so a studio stops setting it per delivery. */
    public function galleryDefaults(): array
    {
        $row = $this->row();

        return [
            'expiryDays' => $row->gallery_default_expiry_days,
            'requiresSettledBalance' => (bool) $row->gallery_default_requires_settled_balance,
        ];
    }

    public function galleryDownloadsEnabled(): bool
    {
        return (bool) $this->row()->gallery_default_downloads_enabled;
    }

    public function galleryListsEnabled(): bool
    {
        return (bool) $this->row()->gallery_default_lists_enabled;
    }

    public function galleryCommentsEnabled(): bool
    {
        return (bool) $this->row()->gallery_default_comments_enabled;
    }

    /** P-05 (#224)'s requests need a recipient and a deadline; both were missing. */
    public function dsgvoRequestRecipient(): ?string
    {
        return $this->row()->dsgvo_request_recipient;
    }

    public function dsgvoRequestDeadlineDays(): int
    {
        return (int) $this->row()->dsgvo_request_deadline_days;
    }

    public function portalContactPhone(): ?string
    {
        return $this->row()->portal_contact_phone;
    }

    public function portalContactEmail(): ?string
    {
        return $this->row()->portal_contact_email;
    }

    public function portalContactResponseTime(): ?string
    {
        return $this->row()->portal_contact_response_time;
    }

    /** P-03's room: no booking yet. */
    public function portalWelcomeInquiry(): ?string
    {
        return $this->row()->portal_welcome_inquiry;
    }

    /** P-02's room: a stock of bookings rather than one. */
    public function portalWelcomeMany(): ?string
    {
        return $this->row()->portal_welcome_many;
    }

    /** Whether the first-run wizard (App\Http\Middleware\RequireStudioOnboarding) has been completed. */
    public function onboardingCompleted(): bool
    {
        return $this->row()->onboarding_completed_at !== null;
    }

    // --- T-11: quiet hours — App\Notifications\Support\QuietHours is the
    // only reader of these three, the same "raw values here, logic in the
    // domain class" split T-13's workingDays()/TaskTemplate::dueFor() already
    // draws.

    public function quietHoursEnabled(): bool
    {
        return $this->row()->quiet_hours_enabled;
    }

    /** "HH:MM:SS", tenant-local — null whenever quietHoursEnabled() is false. */
    public function quietHoursStart(): ?string
    {
        return $this->row()->quiet_hours_start;
    }

    public function quietHoursEnd(): ?string
    {
        return $this->row()->quiet_hours_end;
    }

    private function row(): StudioSettings
    {
        return Cache::store('redis')->remember(
            $this->cacheKey(),
            self::TTL,
            fn () => StudioSettings::query()->firstOrFail(),
        );
    }

    private function cacheKey(): string
    {
        return self::CACHE_KEY_PREFIX.config('tenancy.tenant_id');
    }
}
