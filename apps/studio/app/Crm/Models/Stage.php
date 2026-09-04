<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StageColor;
use App\Crm\Enums\StagePipeline;
use Database\Factories\Crm\StageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One position in one of the two funnels, and the studio's to rename, recolour,
 * reorder or add to.
 *
 * `key` is what leads.stage and shoots.status store, so it is fixed at
 * creation: renaming a stage may not orphan the rows standing on it. Behaviour
 * hangs off `anchor` and never off the key, the name or the position — see
 * StageAnchor.
 */
class Stage extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['pipeline', 'key', 'name', 'color', 'anchor', 'sort_order', 'is_active'];

    protected $attributes = ['is_active' => true, 'sort_order' => 0, 'color' => 'grey'];

    protected function casts(): array
    {
        return [
            'pipeline' => StagePipeline::class,
            'color' => StageColor::class,
            'anchor' => StageAnchor::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopePipeline(Builder $query, StagePipeline $pipeline): void
    {
        $query->where('pipeline', $pipeline);
    }

    /** @return Collection<int, self> */
    public static function ordered(StagePipeline $pipeline): Collection
    {
        return static::query()->pipeline($pipeline)->orderBy('sort_order')->orderBy('id')->get();
    }

    public static function anchored(StagePipeline $pipeline, StageAnchor $anchor): ?self
    {
        return static::query()->pipeline($pipeline)->where('anchor', $anchor)->first();
    }

    /**
     * The rule LeadStage and ShootStatus each wrote out for themselves, now
     * read off the pipeline: the funnel takes a correction backwards, the
     * production pipeline does not (a shoot that returns to gebucht after
     * delivery makes every downstream count untrustworthy). Only the no-op is
     * refused in both — it writes an entry timestamp and an audit row that say
     * nothing happened.
     */
    public function canTransitionTo(self $next): bool
    {
        if ($next->is($this)) {
            return false;
        }

        return $this->pipeline->allowsBackwards() || $next->sort_order > $this->sort_order;
    }

    protected static function newFactory(): Factory
    {
        return StageFactory::new();
    }
}
