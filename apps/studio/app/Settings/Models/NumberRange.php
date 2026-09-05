<?php

namespace App\Settings\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Enums\NumberRangeResetRule;
use App\Numbering\Models\NumberCounter;
use Database\Factories\Settings\NumberRangeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The CONFIGURED Nummernkreis T-04 asked for, one row per tenant per
 * DocumentType — prefix, reset rule and starting counter — that
 * AllocateDocumentNumber reads instead of Q-05's hardcoded 'AN' and
 * assumed-yearly reset.
 */
class NumberRange extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['document_type', 'prefix', 'reset_rule', 'starting_counter'];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'reset_rule' => NumberRangeResetRule::class,
            'starting_counter' => 'integer',
        ];
    }

    /**
     * Whether this range has issued a number for its current period —
     * the current year if it resets Yearly, ever if it resets Continuous.
     * The gate on both starting_counter's settability (App\Settings\Actions\
     * UpdateNumberRange) and the settings screen's own input state.
     */
    public function hasIssued(): bool
    {
        $query = NumberCounter::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('document_type', $this->document_type->value);

        if ($this->reset_rule === NumberRangeResetRule::Yearly) {
            $query->where('year', now('UTC')->year);
        }

        return $query->exists();
    }

    protected static function newFactory(): Factory
    {
        return NumberRangeFactory::new();
    }
}
