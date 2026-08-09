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
 * ships). T-02 (Studio-Profil) extends this table with the rest of
 * mockups/admin/14-einstellungen.html.
 */
class StudioSettings extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['display_name', 'vat_mode', 'vat_rate', 'vat_note'];
}
