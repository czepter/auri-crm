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
 * ships); the studio-profile columns are T-02's; the payments_* columns
 * are T-07's — Stripe as an optional capability plus the deposit,
 * Zahlungsziel and reminder defaults every PaymentPlan reads regardless of
 * whether Stripe is ever switched on.
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
        'payments_stripe_enabled', 'payments_stripe_mode',
        'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
        'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
        'payments_deposit_percent', 'payments_zahlungsziel_days', 'payments_reminders_enabled',
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
            'payments_stripe_enabled' => 'boolean',
            'payments_stripe_test_publishable_key' => 'encrypted',
            'payments_stripe_test_secret_key' => 'encrypted',
            'payments_stripe_live_publishable_key' => 'encrypted',
            'payments_stripe_live_secret_key' => 'encrypted',
            'payments_deposit_percent' => 'integer',
            'payments_zahlungsziel_days' => 'integer',
            'payments_reminders_enabled' => 'boolean',
        ];
    }
}
