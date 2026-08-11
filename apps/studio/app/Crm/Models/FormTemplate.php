<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ProjectType;
use Database\Factories\Crm\FormTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E-01: a reusable questionnaire, built once and reused across projects. A
 * project never holds a live link to one of these — copyFields() (E-02's
 * App\Crm\Actions\SeedFormInstanceFromTemplate) is the only way data leaves
 * here, and it leaves as a plain array with no id attached, the same shape
 * Package::copyItems() gives a quote. That is what makes editing this
 * template afterwards touch nothing already seeded from it.
 */
class FormTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'project_type', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'project_type' => ProjectType::class,
            'is_active' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * A deep, disconnected copy of this template's fields — E-02's seed
     * step, the same shape Package::copyItems() gives Q-02. No id and no
     * form_template_id in the result: carrying either would be the live
     * reference back to this row a FormInstance must never hold, since that
     * is what would let editing this template afterwards move a question a
     * project already started answering.
     *
     * @return list<array{label: string, type: string, required: bool, options: ?array}>
     */
    public function copyFields(): array
    {
        return $this->fields->map(fn (FormField $field) => [
            'label' => $field->label,
            'type' => $field->type->value,
            'required' => $field->required,
            'options' => $field->options,
        ])->all();
    }

    protected static function newFactory(): Factory
    {
        return FormTemplateFactory::new();
    }
}
