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
 * ships); the studio-profile columns are T-02's. quote_validity_days,
 * payment_term_days, deposit_percentage, widerruf_included and widerruf_text
 * are T-05's Dokumente pane — the values every new quote, payment plan and
 * contract render starts from, including the deposit and Zahlungsziel
 * defaults T-07's own issue also named; T-05 landed first, so T-07 reads
 * those two through T-05's columns rather than declaring a second pair. The
 * payments_* columns below are T-07's own remainder: Stripe as an optional
 * capability, and the reminder default T-05 did not cover.
 */
class StudioSettings extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = [
        'display_name',
        'legal_name', 'legal_form', 'street', 'postcode', 'city', 'country',
        'tax_number', 'vat_id', 'register_number', 'register_court', 'representative',
        'website', 'accent_color', 'logo_path',
        'bank_account_holder', 'bank_iban', 'bank_bic',
        'vat_mode', 'vat_rate', 'vat_note',
        'quote_validity_days', 'payment_term_days', 'deposit_percentage', 'widerruf_included', 'widerruf_text',
        'payments_stripe_enabled', 'payments_stripe_mode',
        'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
        'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
        'payments_reminders_enabled',
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
            'payments_stripe_enabled' => 'boolean',
            'payments_stripe_test_publishable_key' => 'encrypted',
            'payments_stripe_test_secret_key' => 'encrypted',
            'payments_stripe_live_publishable_key' => 'encrypted',
            'payments_stripe_live_secret_key' => 'encrypted',
            'payments_reminders_enabled' => 'boolean',
        ];
    }
}
