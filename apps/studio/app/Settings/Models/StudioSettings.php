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
 * ships); the rest below is T-02's Studio-Profil. quote_validity_days,
 * payment_term_days, deposit_percentage, widerruf_included and widerruf_text
 * are T-05's Dokumente pane — the values every new quote, payment plan and
 * contract render starts from.
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
    ];

    protected function casts(): array
    {
        return ['widerruf_included' => 'boolean'];
    }
}
