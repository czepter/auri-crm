<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Enums\AbsenceCategory;
use App\Enums\Capability;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T-10's third availability source: a date range one person is away —
 * holiday, another job, parental leave. No free-text reason column on
 * purpose: `category` is a closed enum precisely so this table can never
 * accumulate the health data Art. 9 DSGVO makes a studio's problem the
 * moment somebody types "krank" into a field designed to hold anything.
 */
class Absence extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['user_id', 'category', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return [
            'category' => AbsenceCategory::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /**
     * The rows AbsencePolicy would let this user open: their own, and
     * everyone's with ManageCrew. Every screen that lists absences reads
     * through here — a filter on the absence screen while the calendar
     * beside it names the same people is no rule.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role->allows(Capability::ManageCrew)) {
            return;
        }

        $query->where('user_id', $user->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether $date (Y-m-d) falls inside this absence, inclusive on both
     * ends — same shape as StudioClosure::coversDate(), which
     * CheckDateAvailability's third source mirrors on purpose.
     */
    public function coversDate(string $date): bool
    {
        return $this->starts_on->format('Y-m-d') <= $date && $this->ends_on->format('Y-m-d') >= $date;
    }

    protected static function newFactory(): Factory
    {
        return AbsenceFactory::new();
    }
}
