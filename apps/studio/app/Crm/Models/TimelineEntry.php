<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\TimelineEntryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a shoot's day-of schedule (E-04): a time, what happens, where,
 * and any note for whoever is doing it. `is_transit` marks a travel leg
 * between two real stops — same row shape as any other entry, rendered
 * lighter because there is nowhere to be, only somewhere to get to.
 */
class TimelineEntry extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['shoot_id', 'time', 'title', 'location', 'note', 'is_transit', 'sort_order'];

    protected function casts(): array
    {
        return ['is_transit' => 'boolean'];
    }

    public function shoot(): BelongsTo
    {
        return $this->belongsTo(Shoot::class);
    }

    protected static function newFactory(): Factory
    {
        return TimelineEntryFactory::new();
    }
}
