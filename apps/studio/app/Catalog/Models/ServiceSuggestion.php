<?php

namespace App\Catalog\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Leistung, die das Studio genau diesem Kunden vorschlägt — mit dem Satz,
 * der im Portal darüber steht.
 *
 * Kein zweites Regal: was jeder Bestandskunde sieht, steht am Paket
 * (portal_offered). Diese Zeile ist das, was jemand von Hand über diese eine
 * Kundenbeziehung geschrieben hat, und sie ist der Grund, warum ein
 * Bestandskunde überhaupt antwortet.
 */
class ServiceSuggestion extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['contact_id', 'package_id', 'note', 'suggested_by_id'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by_id');
    }

    /**
     * Was dieser Kunde tatsächlich zu sehen bekommt. Ein Vorschlag auf ein
     * Paket, das inzwischen nicht mehr im Portal angeboten wird, fällt heraus,
     * ohne gelöscht zu werden: das Studio hat die Leistung zurückgezogen, nicht
     * den Vorschlag widerrufen, und schaltet es sie wieder frei, steht er
     * wieder da.
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereHas('package', fn (Builder $package) => $package->where('portal_offered', true));
    }
}
