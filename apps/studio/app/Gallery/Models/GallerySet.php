<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Media\Models\Asset;
use Database\Factories\Gallery\GallerySetFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein benanntes Fach innerhalb einer Galerie. Die Sortierregel gehört
 * hierher und nicht an die Galerie, weil zwei Fächer derselben Galerie
 * verschiedene Regeln wollen.
 */
class GallerySet extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    public const INTERNAL = 'internal';

    public const GUESTS = 'guests';

    protected $fillable = ['gallery_id', 'name', 'position', 'sort_key', 'sort_dir', 'access'];

    protected $attributes = [
        'position' => 0,
        'sort_key' => 'taken_at',
        'sort_dir' => 'asc',
        'access' => self::INTERNAL,
    ];

    public function isForGuests(): bool
    {
        return $this->access === self::GUESTS;
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    protected static function newFactory(): Factory
    {
        return GallerySetFactory::new();
    }
}
