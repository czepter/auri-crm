<?php

namespace App\Finance\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Finance\Enums\DepreciationMethod;
use App\Finance\Support\FiscalYearLock;
use Database\Factories\Finance\FixedAssetFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A purchase carried over several years. Depreciation is computed from these
 * columns when read, never stored. It is created through an expense and counts
 * in a report only while that expense is booked.
 */
class FixedAsset extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'inventory_no', 'name', 'acquired_at', 'cost_net', 'cost_gross', 'method', 'useful_life_months',
        'afa_category', 'business_share', 'disposed_at', 'disposal_proceeds',
    ];

    protected $attributes = ['business_share' => 100];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'immutable_date',
            'disposed_at' => 'immutable_date',
            'cost_net' => 'integer',
            'cost_gross' => 'integer',
            'useful_life_months' => 'integer',
            'business_share' => 'integer',
            'disposal_proceeds' => 'integer',
            'method' => DepreciationMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset) {
            if ($asset->exists && ! $asset->isDirty()) {
                return;
            }

            $original = fn (string $key) => $asset->exists ? $asset->getOriginal($key)?->year : null;
            $disposalOnly = $asset->exists && array_diff(array_keys($asset->getDirty()), ['disposed_at', 'disposal_proceeds', 'updated_at']) === [];

            if (! $disposalOnly) {
                FiscalYearLock::assertOpen('acquired_at', $asset->acquired_at?->year, $original('acquired_at'));
            }

            if (! $asset->exists || $asset->isDirty('disposed_at')) {
                FiscalYearLock::assertOpen('disposed_at', $asset->disposed_at?->year, $original('disposed_at'));
            }
        });

        static::deleting(fn (self $asset) => FiscalYearLock::assertOpen('acquired_at', $asset->acquired_at->year, $asset->disposed_at?->year));
    }

    /** Anschaffungskosten: gross for a Kleinunternehmer year, net otherwise. */
    public function basis(): int
    {
        return FiscalYear::vatModeFor($this->acquired_at->year) === 'small_business' ? $this->cost_gross : $this->cost_net;
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class, 'asset_id');
    }

    protected static function newFactory(): Factory
    {
        return FixedAssetFactory::new();
    }
}
