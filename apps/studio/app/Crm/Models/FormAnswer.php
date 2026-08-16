<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Models\User;
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

    protected $fillable = ['form_instance_field_id', 'version', 'value', 'contact_id', 'author_id'];

    public function formInstanceField(): BelongsTo
    {
        return $this->belongsTo(FormInstanceField::class);
    }

    /** P-03 (#222): set when the client typed it themselves in the portal. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Set when staff recorded it — off a phone call, usually. Null on both for rows written before either existed. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function newFactory(): Factory
    {
        return FormAnswerFactory::new();
    }
}
