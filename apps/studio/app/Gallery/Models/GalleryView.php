<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * C-21 (#256): one opening of a gallery, folded to one row per visitor per
 * day — the same shape DownloadEvent uses, and for the same reason: a couple
 * showing their parents the pictures is one visit, not forty.
 *
 * No public id: nothing addresses one of these. They are counted and listed.
 */
class GalleryView extends Model
{
    use BelongsToTenant;

    protected $fillable = ['gallery_id', 'contact_id', 'visitor_identifier', 'viewed_on'];

    protected function casts(): array
    {
        return ['viewed_on' => 'immutable_date'];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /** Null where only the password was held — a nameless visitor reads as "Gast". */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
