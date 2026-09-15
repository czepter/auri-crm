<?php

namespace App\Catalog\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Catalog\PackageItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of what a package includes. Shaped after Q-02's QuoteItem on
 * purpose — unit_price is nullable for the same reason there: "Enthaltene
 * Leistung" is a property of the offering, not a price a quote has to fake.
 */
class PackageItem extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['title', 'description', 'quantity', 'unit_price', 'sort_order', 'selectable', 'selected', 'outcome', 'image_path', 'vat_rate'];

    protected $attributes = ['quantity' => 1, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['selectable' => 'boolean', 'selected' => 'boolean'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    protected static function newFactory(): Factory
    {
        return PackageItemFactory::new();
    }
}
