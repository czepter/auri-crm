<?php

namespace Database\Factories;

use App\Enums\AbsenceCategory;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absence>
 */
class AbsenceFactory extends Factory
{
    protected $model = Absence::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => AbsenceCategory::Holiday,
            'starts_on' => '2026-12-24',
            'ends_on' => '2026-12-31',
        ];
    }

    public function spanning(string $startsOn, string $endsOn): static
    {
        return $this->state(fn () => ['starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }
}
