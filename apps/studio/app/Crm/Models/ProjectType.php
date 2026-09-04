<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\ProjectTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The kind of work a project is, as a row rather than the three cases
 * App\Crm\Enums\ProjectType held. `key` is what projects.type stores and is
 * fixed at creation, for the reason Stage::$key is.
 */
class ProjectType extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['key', 'name', 'sort_order', 'is_active', 'is_default'];

    protected $attributes = ['is_active' => true, 'is_default' => false, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The seeded "Sonstige", the one type whose free-text detail Project keeps
     * (Project::booted). A studio may rename it; deleting it means no type
     * carries a detail any more, which is the honest reading of deleting it.
     */
    public function isCatchAll(): bool
    {
        return $this->key === 'other';
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->active()->orderBy('sort_order')->first();
    }

    protected static function newFactory(): Factory
    {
        return ProjectTypeFactory::new();
    }
}
