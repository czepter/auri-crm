<?php

namespace App\Catalog\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Catalog\CatalogItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A remembered quote line. It belongs to nothing and stands in no order — no
 * foreign key points here and none points away, for the same reason
 * Package::copyItems() hands out a disconnected copy: what already stands in
 * a quote must never move again from here.
 *
 * There is no maintenance screen. The tick on a quote line is the only writer,
 * which is also why the title carries the identity: "remembered" is the
 * question whether a row with this title exists, not a column on quote_items.
 */
class CatalogItem extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['title', 'description', 'unit_price'];

    protected static function newFactory(): Factory
    {
        return CatalogItemFactory::new();
    }
}
