<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\FormFieldType;
use Database\Factories\Crm\FormInstanceFieldFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question on a FormInstance. Shaped after FormField on purpose — same
 * columns, same wholesale-replace-on-save relationship — but this is the
 * project's own copy: editing it never writes back to the FormField rows it
 * may have started as (see FormInstance's own doc comment).
 */
class FormInstanceField extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['label', 'type', 'required', 'options', 'sort_order'];

    protected $attributes = ['required' => false, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function formInstance(): BelongsTo
    {
        return $this->belongsTo(FormInstance::class);
    }

    protected static function newFactory(): Factory
    {
        return FormInstanceFieldFactory::new();
    }
}
