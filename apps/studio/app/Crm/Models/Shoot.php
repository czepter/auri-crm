<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\StagePipeline;
use App\Crm\Support\CrmVocabulary;
use BackedEnum;
use Database\Factories\Crm\ShootFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One shooting occasion: a date, a place, a crew and its own pipeline.
 *
 * Hangs off ClientCase whether booked or not, so a requested date and a booked date are
 * the same row in two states rather than two records kept in sync (vision
 * §3.1). The row checked for availability at intake is the row later booked.
 */
class Shoot extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'case_id',
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
        'status' => 'requested',
        'is_primary' => false,
        'sort_order' => 0,
    ];

    /**
     * The stage row behind the stored key. See Lead::stage() for why this is
     * an accessor rather than a cast class.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(CrmVocabulary::class)->byKey(StagePipeline::Project, $value),
            set: fn (Stage|BackedEnum|string|null $value) => match (true) {
                $value === null => null,
                $value instanceof Stage => $value->key,
                $value instanceof BackedEnum => (string) $value->value,
                default => $value,
            },
            // Or an assigned enum would be handed back unchanged: Eloquent keeps
            // whatever object was set, and the seed enums are objects.
        )->withoutObjectCaching();
    }

    public static function kindFor(?ProjectType $type): string
    {
        return $type?->name ?? 'Sonstige';
    }

    /** How long a shoot without an end_time runs: what its project's type says. */
    public function defaultDurationMinutes(): int
    {
        return $this->case->type?->duration_minutes ?? 120;
    }

    /** @return list<string> */
    public static function kindSuggestions(): array
    {
        return static::query()->distinct()->orderBy('kind')->pluck('kind')->all();
    }

    protected function casts(): array
    {
        return [
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

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class);
    }

    public function crew(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Ordered so every reader — the screen, the PDF, the ICS feed — walks the day the same way. */
    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEntry::class)->orderBy('time')->orderBy('sort_order');
    }

    public function shotListItems(): HasMany
    {
        return $this->hasMany(ShotListItem::class);
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
