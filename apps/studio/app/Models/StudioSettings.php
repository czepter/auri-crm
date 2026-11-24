<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Settings\Enums\ContractMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * One row per tenant (T-01), read through current(), which caches it on the
 * Redis store the web and worker processes share. Writes go through the
 * Settings Actions, which also leave the audit row. display_name is T-01's scaffolding column; vat_mode, vat_rate and
 * vat_note are T-03's (Zahlungsdaten & USt — Q-04 reads these once it
 * ships); timezone
 * and working_days are T-13's (Allgemein); the rest below is T-02's
 * Studio-Profil. quote_validity_days, payment_term_days,
 * and deposit_percentage are T-05's
 * Dokumente pane — the values every new quote and payment plan
 * starts from, including the deposit and Zahlungsziel defaults
 * T-07's own issue also named; T-05 landed first, so T-07 reads those two
 * through T-05's columns rather than declaring a second pair. The
 * payments_* columns below are T-07's own remainder: Stripe as an optional
 * capability, and the reminder default T-05 did not cover. mail_from_name,
 * mail_from_address, mail_reply_to, mail_signature and mail_bcc are T-06's
 * (E-Mail) — who the studio's mail comes from, and what it says at the
 * bottom. The portal_* columns are T-08's: a maintenance closure switch, a
 * home-only welcome text, two external legal links, and four client
 * capability switches — see that migration's own doc comment for why each
 * defaults where it does.
 */
class StudioSettings extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const DELETION_GRACE_DAYS = 30;

    // Redis, not the default cache: the web and the worker must see the same row.
    private const CACHE_KEY_PREFIX = 'studio_settings:';

    private const TTL = 3600;

    protected $fillable = [
        'display_name',
        'legal_name', 'legal_form', 'street', 'postcode', 'city', 'country',
        'tax_number', 'vat_id', 'register_number', 'register_court', 'representative',
        'website', 'accent_color', 'logo_path', 'font_pair',
        'bank_account_holder', 'bank_iban', 'bank_bic',
        'vat_mode', 'vat_rate', 'vat_note', 'document_layout', 'client_register',
        'quote_validity_days', 'payment_term_days', 'deposit_percentage',
        'document_letterhead_html', 'document_footer_html',
        'timezone', 'working_days',
        'payments_stripe_enabled', 'payments_stripe_mode',
        'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
        'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
        'payments_reminders_enabled',
        'mail_from_name', 'mail_from_address', 'mail_reply_to', 'mail_signature', 'mail_bcc', 'mail_clients_enabled',
        'mail_defaults', 'mail_header_name', 'mail_footer_legal', 'mail_footer_extra',
        'portal_maintenance_enabled', 'portal_maintenance_message',
        'portal_legal_datenschutz_url', 'portal_legal_agb_url',
        'portal_legal_impressum_url', 'portal_legal_widerruf_url',
        'portal_legal_impressum_family', 'portal_legal_datenschutz_family',
        'portal_legal_agb_family', 'portal_legal_widerruf_family',
        'portal_legal_agb_attach', 'portal_legal_widerruf_attach',
        'contract_mode', 'portal_accept_quotes_enabled', 'portal_sign_contracts_enabled',
        'portal_payment_status_enabled', 'portal_messaging_mode', 'portal_offers_enabled',
        'portal_welcome_inquiry', 'portal_welcome_active', 'portal_welcome_done',
        'gallery_default_expiry_days',
        'gallery_default_download_rule', 'gallery_default_lists_enabled',
        'gallery_default_comments_enabled', 'gallery_default_design',
        'gallery_home_mode', 'gallery_home_redirect_url', 'gallery_home_noindex',
        'portal_contact_name', 'portal_contact_phone', 'portal_contact_email',
        'portal_contact_response_time', 'portal_contact_avatar_path',
        'onboarding_completed_at', 'onboarding_reminder_sent_at', 'deletion_requested_at',
        'retention_months', 'retention_policy',
    ];

    // current() hands out the whole model, so the keys are guarded here: the
    // 'encrypted' casts keep them ciphertext at rest, and $hidden keeps them
    // out of toArray()/toJson() and so out of any Inertia prop — the same belt
    // the four MFA columns wear on App\Models\User.
    protected $hidden = [
        'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
        'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
    ];

    private static ?self $unsaved = null;

    /** Runs $callback with unsaved values standing in for the stored settings, as a preview needs. */
    public static function withUnsaved(array $attributes, \Closure $callback): mixed
    {
        $previous = self::$unsaved;
        self::$unsaved = (clone static::current())->forceFill($attributes);

        try {
            return $callback();
        } finally {
            self::$unsaved = $previous;
        }
    }

    public static function current(): self
    {
        if (self::$unsaved !== null) {
            return self::$unsaved;
        }

        $store = Cache::store('redis');
        $key = self::CACHE_KEY_PREFIX.config('tenancy.tenant_id');
        $cached = $store->get($key);

        if ($cached instanceof self) {
            return $cached;
        }

        $row = static::query()->firstOrFail();
        $store->put($key, $row, self::TTL);

        return $row;
    }

    public static function forgetCurrent(): void
    {
        Cache::store('redis')->forget(self::CACHE_KEY_PREFIX.config('tenancy.tenant_id'));
    }

    public function contractMode(): ContractMode
    {
        return ContractMode::tryFrom((string) $this->contract_mode) ?? ContractMode::OnDemand;
    }

    /** config('mail.from.*') stands in until the E-Mail pane sets the tenant's own. */
    public function mailFromName(): string
    {
        return $this->mail_from_name ?: config('mail.from.name');
    }

    public function mailFromAddress(): string
    {
        return $this->mail_from_address ?: config('mail.from.address');
    }

    /**
     * IANA identifier (e.g. "Europe/Vienna") — the tenant value once T-13's
     * Allgemein pane has set one, config('app.timezone') until then. A
     * studio that has not opened this pane yet sees exactly what it saw
     * before this story existed.
     */
    public function localTimezone(): string
    {
        return $this->timezone ?? config('app.timezone');
    }

    /**
     * ISO weekday integers (1 Monday .. 7 Sunday). An empty set reads as
     * every day: TaskTemplate::dueFor() loops on it, and an empty one would
     * hang a request.
     *
     * @return list<int>
     */
    public function workingDays(): array
    {
        return empty($this->working_days) ? [1, 2, 3, 4, 5, 6, 7] : $this->working_days;
    }

    /**
     * T-02's legal-entity, brand and bank columns, keyed by column name, as
     * plain values — what the Studio-Profil screen and the `studio.*` merge
     * fields read.
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return Arr::only($this->getAttributes(), [
            'display_name',
            'legal_name', 'legal_form', 'street', 'postcode', 'city', 'country',
            'tax_number', 'vat_id', 'register_number', 'register_court', 'representative',
            'website', 'accent_color', 'logo_path', 'font_pair',
            'bank_account_holder', 'bank_iban', 'bank_bic',
        ]);
    }

    /**
     * T-02's postal particulars as the one line a footer wears, composed
     * from the parts a studio filled in: a studio with a city and no street
     * still has an address to name.
     */
    public function postalLine(): ?string
    {
        $line = implode(', ', array_filter([
            $this->street,
            implode(' ', array_filter([$this->postcode, $this->city])),
        ]));

        return $line === '' ? null : $line;
    }

    /**
     * #439: how a client text addresses its readers. Informal splits on the
     * count, so a couple reads "ihr" where a single contact reads "du".
     *
     * @return 'sie'|'du'|'ihr'
     */
    public function clientAddress(int $recipients = 1): string
    {
        return match (true) {
            $this->client_register === 'formal' => 'sie',
            $recipients > 1 => 'ihr',
            default => 'du',
        };
    }

    /**
     * What a document actually prints — zero under small_business whatever
     * vat_rate holds, so a mode switch never leaves a stale 19% behind. The
     * stored rate stays, or switching back to standard has nothing to restore.
     */
    public function effectiveVatRate(): int
    {
        return $this->vat_mode === 'small_business' ? 0 : $this->vat_rate;
    }

    /** Der Löschtag: Tag des Antrags plus Frist, in der Zeit des Studios. Ab dessen Morgen läuft DeleteTenant. */
    public function deletionDate(): ?CarbonImmutable
    {
        return $this->deletion_requested_at
            ?->toImmutable()
            ->setTimezone($this->localTimezone())
            ->addDays(self::DELETION_GRACE_DAYS)
            ->startOfDay();
    }

    /** Whether the first-run wizard (App\Http\Middleware\RequireStudioOnboarding) has been completed. */
    public function onboardingCompleted(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /** Whether App\Staff\Actions\RemindOnboarding has already sent its one nudge. */
    public function onboardingReminderSent(): bool
    {
        return $this->onboarding_reminder_sent_at !== null;
    }

    protected function casts(): array
    {
        return [
            'portal_legal_agb_attach' => 'boolean',
            'portal_legal_widerruf_attach' => 'boolean',
            // ISO weekday integers (1 Monday .. 7 Sunday) — see
            // StudioSettings::workingDays().
            'working_days' => 'array',
            'payments_stripe_enabled' => 'boolean',
            'payments_stripe_test_publishable_key' => 'encrypted',
            'payments_stripe_test_secret_key' => 'encrypted',
            'payments_stripe_live_publishable_key' => 'encrypted',
            'payments_stripe_live_secret_key' => 'encrypted',
            'payments_reminders_enabled' => 'boolean',
            'mail_clients_enabled' => 'boolean',
            'mail_defaults' => 'array',
            'mail_header_name' => 'boolean',
            'mail_footer_legal' => 'boolean',
            'portal_maintenance_enabled' => 'boolean',
            'portal_accept_quotes_enabled' => 'boolean',
            'portal_sign_contracts_enabled' => 'boolean',
            'portal_payment_status_enabled' => 'boolean',
            'portal_offers_enabled' => 'boolean',
            'gallery_default_lists_enabled' => 'boolean',
            'gallery_default_comments_enabled' => 'boolean',
            'gallery_default_design' => 'array',
            'onboarding_completed_at' => 'datetime',
            'onboarding_reminder_sent_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'retention_months' => 'integer',
            'retention_policy' => 'array',
        ];
    }
}
