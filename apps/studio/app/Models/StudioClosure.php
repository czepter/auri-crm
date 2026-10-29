<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Settings\StudioClosureFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A date range the studio is closed — T-09's second availability source,
 * typed once by hand in the Verfügbarkeit settings pane. CheckDateAvailability
 * reads this table alongside shoots.date; see that class for why both sources
 * land in one query rather than one call per date.
 */
class StudioClosure extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['label', 'starts_on', 'ends_on', 'message'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /** Whether $date (Y-m-d) falls inside this closure, inclusive on both ends. */
    public function coversDate(string $date): bool
    {
        return $this->starts_on->format('Y-m-d') <= $date && $this->ends_on->format('Y-m-d') >= $date;
    }

    protected static function newFactory(): Factory
    {
        return StudioClosureFactory::new();
    }
}
