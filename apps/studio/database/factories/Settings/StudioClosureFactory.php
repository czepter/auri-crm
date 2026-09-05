<?php

namespace Database\Factories\Settings;

use App\Settings\Models\StudioClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudioClosure>
 */
class StudioClosureFactory extends Factory
{
    protected $model = StudioClosure::class;

    public function definition(): array
    {
        return [
            'label' => 'Betriebsferien',
            'starts_on' => '2026-12-24',
            'ends_on' => '2026-12-31',
        ];
    }

    public function spanning(string $startsOn, string $endsOn): static
    {
        return $this->state(fn () => ['starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }
}
