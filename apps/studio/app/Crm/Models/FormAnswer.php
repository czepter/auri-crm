<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\FormAnswerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E-03: one exact, immutable version of a FormInstanceField's answer. Never
 * updated after creation — editing an answer never touches an existing row,
 * only appends a new one via FormInstanceField::answer(), the same
 * append-only shape GalleryVersion and DocumentTemplate give their own
 * families. A version already read (its value, its created_at) stays exactly
 * what it was no matter how many times the field is answered again
 * afterwards; the "current" answer is simply the highest version.
 */
class FormAnswer extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['form_instance_field_id', 'version', 'value'];

    public function formInstanceField(): BelongsTo
    {
        return $this->belongsTo(FormInstanceField::class);
    }

    protected static function newFactory(): Factory
    {
        return FormAnswerFactory::new();
    }
}
