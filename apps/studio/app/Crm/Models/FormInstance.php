<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\FormInstanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E-02: a project's own questionnaire. Seeded from a FormTemplate
 * (App\Crm\Actions\SeedFormInstanceFromTemplate, reading
 * FormTemplate::copyFields()) or started blank, then personalised freely.
 *
 * Holds no form_template_id and its fields carry no form_field_id — the same
 * "deep, disconnected copy" shape Package::copyItems() gives a QuoteItem.
 * source_template_name is provenance only, a plain string snapshotted at
 * seed time, never a reference this instance looks back through. That is
 * what the acceptance criterion turns on: editing, renaming or deleting the
 * template afterwards touches nothing already copied out of it.
 */
class FormInstance extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['case_id', 'name', 'source_template_name'];

    protected function casts(): array
    {
        return ['shared_at' => 'immutable_datetime'];
    }

    /**
     * P-03 (#222): has the studio put this questionnaire in front of the
     * client? Per questionnaire and not per project — a studio keeps its own
     * checklists on this same table, and those are nobody else's business.
     */
    public function isShared(): bool
    {
        return $this->shared_at !== null;
    }

    public function scopeShared(Builder $query): void
    {
        $query->whereNotNull('shared_at');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormInstanceField::class)->orderBy('sort_order');
    }

    protected static function newFactory(): Factory
    {
        return FormInstanceFactory::new();
    }
}
