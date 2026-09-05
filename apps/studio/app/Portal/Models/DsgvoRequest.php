<?php

namespace App\Portal\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P-05 (#224). A client asking for their data or its deletion, from inside
 * the portal, as a request the studio has to answer.
 *
 * Not a button that does it. The export is a document a studio may want to
 * check before it leaves, and a deletion cannot simply happen: signed
 * contracts and issued invoices carry a ten-year retention (§ 147 AO,
 * § 257 HGB), which is exactly what ContactDsgvoController already tells
 * staff and what the portal screen says instead of promising a deletion that
 * would not be one.
 *
 * X-04's ExportContactDsgvoData and EraseContactDsgvo remain the admin-side
 * actions that answer one of these.
 */
class DsgvoRequest extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const EXPORT = 'export';

    public const ERASURE = 'erasure';

    protected $fillable = ['contact_id', 'kind'];

    protected function casts(): array
    {
        return ['handled_at' => 'immutable_datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('handled_at');
    }
}
