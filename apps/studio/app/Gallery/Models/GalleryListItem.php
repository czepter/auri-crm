<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One picture on one list. asset_key rather than a foreign key: assets live
 * inside a GalleryVersion's JSON and there is no table to point at — see the
 * migration's own note on why that is also what carries a list across a
 * re-publish.
 */
class GalleryListItem extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['gallery_list_id', 'asset_key', 'position'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(GalleryList::class, 'gallery_list_id');
    }
}
