<?php

namespace App\Media\Models;

use App\Concerns\BelongsToTenant;
use App\Gallery\Models\GalleryGuest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Gast, der auf dieses Archiv wartet. Ein Bau kann mehrere haben —
 * zwei Gäste, die dasselbe anfordern, teilen sich die Bänder.
 */
class ZipBuildWaiter extends Model
{
    use BelongsToTenant;

    protected $fillable = ['zip_build_id', 'gallery_guest_id', 'notified_at'];

    protected function casts(): array
    {
        return ['notified_at' => 'immutable_datetime'];
    }

    public function zipBuild(): BelongsTo
    {
        return $this->belongsTo(ZipBuild::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(GalleryGuest::class, 'gallery_guest_id');
    }
}
