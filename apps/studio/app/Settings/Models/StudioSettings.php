<?php

namespace App\Settings\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per tenant (T-01) — never read directly outside
 * StudioSettingsResolver, which is what caches this row on the Redis store
 * the web and worker processes share, and what turns a write into an audit
 * row. display_name is T-01's scaffolding column; T-02 (Studio-Profil) adds
 * the legal-entity, brand and bank columns below, reusing display_name for
 * "Anzeigename" rather than duplicating it. T-03 (Zahlungsdaten & USt) still
 * has its own columns to add on top of these.
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
    ];
}
