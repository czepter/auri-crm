<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ProjectFieldType;
use Database\Factories\Crm\ProjectFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a project of this type carries: Gästezahl, Location-Art, Stil.
 *
 * `key` is what cases.field_values stores and is fixed at creation, for the
 * reason Stage::$key is — the label is the studio's to change, the key is what
 * the stored values hang on.
 */
class ProjectField extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_type_id', 'key', 'label', 'type', 'options', 'unit',
        'required', 'allow_new_options', 'is_active', 'sort_order',
    ];

    protected $attributes = [
        'required' => false,
        'allow_new_options' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => ProjectFieldType::class,
            'options' => 'array',
            'required' => 'boolean',
            'allow_new_options' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    /**
     * A slug of the label, unique per type and fixed at creation — the rule
     * ProjectTypeController::key() writes for a type, applied one level down.
     */
    public static function keyFor(ProjectType $type, string $label): string
    {
        $base = str($label)->slug('_')->limit(60, '')->toString() ?: 'feld';
        $key = $base;
        $suffix = 2;

        while ($type->fields()->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    /** How many projects would lose a value if this field went away. */
    public function standingValues(): int
    {
        return ClientCase::query()->whereNotNull('field_values->'.$this->key)->count();
    }

    protected static function newFactory(): Factory
    {
        return ProjectFieldFactory::new();
    }
}
