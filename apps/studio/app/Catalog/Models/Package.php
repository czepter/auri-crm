<?php

namespace App\Catalog\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Catalog\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable service definition a quote starts from (Q-02): what the studio
 * offers, named once instead of typed onto every quote from scratch.
 *
 * A package is a starting point, never a live link. Nothing anywhere holds a
 * foreign key from a quote back to this row — copyItems() is the only way
 * data leaves here, and it leaves as a plain array with no id attached. That
 * is what makes editing or deactivating a package afterwards touch nothing
 * already built from it: there is no reference left to drift.
 */
class Package extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class)->orderBy('sort_order');
    }

    /** Available to start a new quote from. A deactivated package drops out of this and nothing else. */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * A deep, disconnected copy of this package's items — Q-02's seed step.
     * No id and no package_id in the result, on purpose: carrying either would
     * be the live reference back to this row that a package must never hold,
     * since it is what would let editing or deactivating the package here
     * move a line a quote already copied out of it.
     *
     * @return list<array{title: string, description: ?string, quantity: int, unit_price: ?int}>
     */
    public function copyItems(): array
    {
        return $this->items->map(fn (PackageItem $item) => [
            'title' => $item->title,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ])->all();
    }

    protected static function newFactory(): Factory
    {
        return PackageFactory::new();
    }
}
