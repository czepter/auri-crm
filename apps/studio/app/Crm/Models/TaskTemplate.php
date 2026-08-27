<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\TaskAudience;
use App\Crm\Support\TaskAnchors;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;
use Database\Factories\Crm\TaskTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The studio's standing list of what a booked shoot owes, and when.
 */
class TaskTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['title', 'audience', 'day_offset', 'shoot_kind', 'is_active', 'sort_order', 'depends_on_template_id'];

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
     *
     * T-13: an offset that lands on a day the studio does not work
     * (StudioSettingsResolver::workingDays()) rolls FORWARD, one day at a
     * time, to the next day that is one — never backward, and never to the
     * "nearest" one. Forward is the conservative direction for a deadline:
     * a task that slips a day or two later is merely later, but pulling it
     * earlier could make it due before whatever it depends on (the shoot
     * itself, or an earlier task) is even ready. $settings is a parameter
     * rather than app(StudioSettingsResolver::class) reached for inside
     * this method, per that class's own rule — a caller's dependency on
     * the settings store shows up in its own constructor, and
     * ApplyTaskTemplates is that caller here.
     */
    public function dueFor(Shoot $shoot, StudioSettingsResolver $settings): ?CarbonImmutable
    {
        $date = $shoot->date?->addDays($this->day_offset);

        if ($date === null) {
            return null;
        }

        return TaskAnchors::workingDay($date, $settings);
    }

    protected static function newFactory(): Factory
    {
        return TaskTemplateFactory::new();
    }
}
