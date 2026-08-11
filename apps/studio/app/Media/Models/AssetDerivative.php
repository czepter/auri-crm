<?php

namespace App\Media\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Media\AssetDerivativeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rung of an Asset's derivative ladder (G-03): one width, one format
 * (avif|webp), one object key. `bytes` is what G-10's ZIP build reads to emit
 * a ZIP64-free central directory (member sizes known up front) — see the P3
 * gallery spike, §3.
 */
class AssetDerivative extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['asset_id', 'width', 'format', 'key', 'bytes'];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'bytes' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected static function newFactory(): Factory
    {
        return AssetDerivativeFactory::new();
    }
}
