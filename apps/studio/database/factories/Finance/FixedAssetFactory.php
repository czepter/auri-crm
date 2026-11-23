<?php

namespace Database\Factories\Finance;

use App\Finance\Enums\DepreciationMethod;
use App\Finance\Models\FixedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedAsset>
 */
class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    public function definition(): array
    {
        $year = now()->year - 1;

        return [
            'inventory_no' => 'INV-'.substr((string) $year, 2).'-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'name' => 'Kamera-Body',
            'acquired_at' => "$year-03-10",
            'cost_net' => 240000,
            'cost_gross' => 285600,
            'method' => DepreciationMethod::Linear,
            'useful_life_months' => 36,
            'business_share' => 100,
        ];
    }

    public function gwg(): static
    {
        return $this->state(fn () => ['method' => DepreciationMethod::Gwg, 'useful_life_months' => null, 'cost_net' => 64000, 'cost_gross' => 76160]);
    }
}
