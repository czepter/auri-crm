<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\ShotListItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shot the studio means to get on a shoot, checked off as it happens.
 */
class ShotListItem extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['shoot_id', 'label', 'checked_off_at', 'sort_order'];

    protected function casts(): array
    {
        return ['checked_off_at' => 'immutable_datetime'];
    }

    public function isCheckedOff(): bool
    {
        return $this->checked_off_at !== null;
    }

    public function shoot(): BelongsTo
    {
        return $this->belongsTo(Shoot::class);
    }

    protected static function newFactory(): Factory
    {
        return ShotListItemFactory::new();
    }
}
