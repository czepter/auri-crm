<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Gallery\GalleryVersionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One exact, immutable publish of a Gallery — a snapshot of title/assets at
 * the moment Gallery::publish() ran. Never updated after creation: a guest
 * (or a cache, per G-08) already pointing at this row must keep seeing
 * exactly what it saw the day it was published, no matter how many times the
 * gallery's draft state is edited and republished afterwards.
 */
class GalleryVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['gallery_id', 'version', 'title', 'assets', 'published_at'];

    protected function casts(): array
    {
        return [
            'assets' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    protected static function newFactory(): Factory
    {
        return GalleryVersionFactory::new();
    }
}
