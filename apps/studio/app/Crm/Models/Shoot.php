<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use Database\Factories\Crm\ShootFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One shooting occasion: a date, a place, a crew and its own pipeline.
 *
 * Hangs off Project rather than Job, so a requested date and a booked date are
 * the same row in two states rather than two records kept in sync (vision
 * §3.1). The row checked for availability at intake is the row later booked.
 */
class Shoot extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_id',
        'kind',
        'label',
        'date',
        'call_time',
        'end_time',
        'venue_name',
        'venue_city',
        'status',
        'status_entered_at',
        'cancelled_at',
        'cancelled_reason',
        'is_primary',
        'sort_order',
    ];

    protected $attributes = [
        'status' => ShootStatus::Requested->value,
        'is_primary' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => ShootKind::class,
            'status' => ShootStatus::class,
            'date' => 'immutable_date',
            'status_entered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * The column carries a database default for the writers that reach the
     * table directly, but a default only exists on disk: without this the
     * freshly created model reports null until it is reloaded, and a staleness
     * read that meets null skips exactly the newest rows.
     */
    protected static function booted(): void
    {
        static::creating(function (self $shoot) {
            $shoot->status_entered_at ??= now();
        });
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Still counted. Every derived read — C-06's header, P-04's board — goes
     * through here rather than repeating the null check and drifting from it.
     */
    public function scopeUncancelled(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function crew(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Null rather than an empty string, so a caller can test it directly. */
    public function venue(): ?string
    {
        $parts = array_filter([$this->venue_name, $this->venue_city]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    protected static function newFactory(): Factory
    {
        return ShootFactory::new();
    }
}
