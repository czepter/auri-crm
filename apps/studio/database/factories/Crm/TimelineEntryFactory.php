<?php

namespace Database\Factories\Crm;

use App\Crm\Models\Shoot;
use App\Crm\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimelineEntry>
 */
class TimelineEntryFactory extends Factory
{
    protected $model = TimelineEntry::class;

    public function definition(): array
    {
        return [
            'shoot_id' => Shoot::factory(),
            'time' => '09:00',
            'title' => fake()->sentence(3),
            'location' => fake()->streetAddress(),
            'note' => null,
            'is_transit' => false,
            'sort_order' => 0,
        ];
    }

    public function at(string $time): static
    {
        return $this->state(fn () => ['time' => $time]);
    }

    public function transit(): static
    {
        return $this->state(fn () => ['is_transit' => true, 'location' => null]);
    }
}
