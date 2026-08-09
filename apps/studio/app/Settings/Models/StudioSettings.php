<?php

namespace App\Settings\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per tenant (T-01) — never read directly outside
 * StudioSettingsResolver, which is what caches this row on the Redis store
 * the web and worker processes share, and what turns a write into an audit
 * row. display_name is scaffolding: the only column this story adds, so the
 * resolver has something typed to prove itself against. T-02 (Studio-Profil)
 * and T-03 (Zahlungsdaten & USt) extend this table with the rest of
 * mockups/admin/14-einstellungen.html.
 */
class StudioSettings extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['display_name'];
}
