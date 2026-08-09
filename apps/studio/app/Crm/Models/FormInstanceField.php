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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

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

    public function answers(): HasMany
    {
        return $this->hasMany(FormAnswer::class);
    }

    /** The latest answer version, or null before this field has ever been answered. */
    public function currentAnswer(): HasOne
    {
        return $this->hasOne(FormAnswer::class)->latestOfMany('version');
    }

    /**
     * E-03: records a new answer version and leaves every earlier one
     * untouched — no update, no delete. Covers both the first submission and
     * every edit afterwards; there is no separate "draft" to publish, each
     * call is itself the new current version. Same transaction shape as
     * Gallery::publish() and DocumentTemplate::newVersion(): the max-version
     * read and the insert happen together, so two concurrent answers either
     * serialize on it or one loses the unique (tenant_id, field, version)
     * index, never both landing the same version number.
     */
    public function answer(?string $value): FormAnswer
    {
        return DB::transaction(function () use ($value) {
            $next = 1 + (int) $this->answers()->max('version');

            return $this->answers()->create([
                'version' => $next,
                'value' => $value,
            ]);
        });
    }

    protected static function newFactory(): Factory
    {
        return FormInstanceFieldFactory::new();
    }
}
