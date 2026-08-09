<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\TaskAudience;
use Database\Factories\Crm\TaskTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonImmutable;

/**
 * The studio's standing list of what a booked shoot owes, and when.
 */
class TaskTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['title', 'audience', 'day_offset', 'shoot_kind', 'is_active', 'sort_order'];

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'audience' => TaskAudience::class,
            'shoot_kind' => ShootKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Null shoot_kind applies to every occasion. */
    public function appliesTo(Shoot $shoot): bool
    {
        return $this->shoot_kind === null || $this->shoot_kind === $shoot->kind;
    }

    /**
     * The due date for one shoot. Null when the shoot has no date yet — a task
     * still worth creating, since the work is owed either way, but there is no
     * honest day to put on it until the shoot has one.
     */
    public function dueFor(Shoot $shoot): ?CarbonImmutable
    {
        return $shoot->date?->addDays($this->day_offset);
    }

    protected static function newFactory(): Factory
    {
        return TaskTemplateFactory::new();
    }
}
