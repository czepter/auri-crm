<?php

namespace App\Settings\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per tenant (T-01) — never read directly outside
 * StudioSettingsResolver, which is what caches this row on the Redis store
 * the web and worker processes share, and what turns a write into an audit
 * row. display_name is T-01's scaffolding column; vat_mode, vat_rate and
 * vat_note are T-03's (Zahlungsdaten & USt — Q-04 reads these once it
 * ships); default_shoot_duration_minutes is T-09's (Verfügbarkeit —
 * ShootCalendar reads it instead of a hardcoded addHours(2)); timezone,
 * locale and working_days are T-13's (Allgemein); the rest below is T-02's
 * Studio-Profil. quote_validity_days, payment_term_days,
 * deposit_percentage, widerruf_included and widerruf_text are T-05's
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
 * defaults where it does. quiet_hours_enabled, quiet_hours_start and
 * quiet_hours_end are T-11's — App\Notifications\Support\QuietHours is the
 * only reader.
 */
class StudioSettings extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = [
        'display_name',
        'legal_name', 'legal_form', 'street', 'postcode', 'city', 'country',
        'tax_number', 'vat_id', 'register_number', 'register_court', 'representative',
        'website', 'accent_color', 'logo_path', 'showcase_paths', 'font_pair',
        'bank_account_holder', 'bank_iban', 'bank_bic',
        'vat_mode', 'vat_rate', 'vat_note', 'quote_layout', 'client_register',
        'default_shoot_duration_minutes',
        'quote_validity_days', 'payment_term_days', 'deposit_percentage', 'widerruf_included', 'widerruf_text',
        'document_letterhead_html', 'document_footer_html',
        'timezone', 'locale', 'working_days',
        'payments_stripe_enabled', 'payments_stripe_mode',
        'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
        'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
        'payments_reminders_enabled',
        'mail_from_name', 'mail_from_address', 'mail_reply_to', 'mail_signature', 'mail_bcc', 'mail_clients_enabled',
        'portal_maintenance_enabled', 'portal_maintenance_message',
        'portal_welcome_text', 'portal_legal_datenschutz_url', 'portal_legal_agb_url',
        'portal_accept_quotes_enabled',
        'portal_payment_status_enabled', 'portal_messaging_mode', 'portal_offers_enabled',
        'portal_welcome_inquiry', 'portal_welcome_many',
        'gallery_default_expiry_days', 'gallery_default_requires_settled_balance',
        'gallery_default_downloads_enabled', 'gallery_default_lists_enabled',
        'gallery_default_comments_enabled', 'gallery_default_design',
        'dsgvo_request_recipient', 'dsgvo_request_deadline_days',
        'portal_contact_phone', 'portal_contact_email', 'portal_contact_response_time',
        'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end',
        'onboarding_completed_at', 'onboarding_reminder_sent_at',
        'lead_type_presets', 'lead_source_presets', 'lead_channel_presets',
    ];

    // Defence in depth beside StudioSettingsResolver never exposing these
    // past itself (T-01's own rule) — the same belt the four MFA columns
    // wear on App\Models\User. A secret key must never ride along in a
    // serialised StudioSettings even by accident.
    protected $hidden = [
        'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
        'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
    ];

    protected function casts(): array
    {
        return [
            'widerruf_included' => 'boolean',
            // ISO weekday integers (1 Monday .. 7 Sunday) — see
            // StudioSettingsResolver::workingDays().
            'working_days' => 'array',
            'payments_stripe_enabled' => 'boolean',
            'payments_stripe_test_publishable_key' => 'encrypted',
            'payments_stripe_test_secret_key' => 'encrypted',
            'payments_stripe_live_publishable_key' => 'encrypted',
            'payments_stripe_live_secret_key' => 'encrypted',
            'payments_reminders_enabled' => 'boolean',
            'mail_clients_enabled' => 'boolean',
            'portal_maintenance_enabled' => 'boolean',
            'portal_accept_quotes_enabled' => 'boolean',
            'portal_payment_status_enabled' => 'boolean',
            'portal_offers_enabled' => 'boolean',
            'gallery_default_requires_settled_balance' => 'boolean',
            'gallery_default_downloads_enabled' => 'boolean',
            'gallery_default_lists_enabled' => 'boolean',
            'gallery_default_comments_enabled' => 'boolean',
            'gallery_default_design' => 'array',
            'quiet_hours_enabled' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'onboarding_reminder_sent_at' => 'datetime',
            'showcase_paths' => 'array',
            'lead_type_presets' => 'array',
            'lead_source_presets' => 'array',
            'lead_channel_presets' => 'array',
        ];
    }
}
