<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\FormFieldType;
use Database\Factories\Crm\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question on a FormTemplate. Shaped after Q-02/Q-01's item pattern —
 * PackageItem, QuoteItem — the same wholesale-replace-on-save relationship,
 * not a diffed sync.
 */
class FormField extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['label', 'type', 'required', 'help', 'options', 'sort_order'];

    protected $attributes = ['required' => false, 'sort_order' => 0];

    protected static function booted(): void
    {
        static::saving(function (self $field) {
            if ($field->type === FormFieldType::Section) {
                $field->required = false;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    protected static function newFactory(): Factory
    {
        return FormFieldFactory::new();
    }
}
