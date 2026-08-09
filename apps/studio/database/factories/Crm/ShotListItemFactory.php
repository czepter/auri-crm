<?php

namespace Database\Factories\Crm;

use App\Crm\Models\Shoot;
use App\Crm\Models\ShotListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShotListItemFactory extends Factory
{
    protected $model = ShotListItem::class;

    public function definition(): array
    {
        return [
            'shoot_id' => Shoot::factory(),
            'label' => 'Brautpaar am Altar',
        ];
    }

    public function checkedOff(): static
    {
        return $this->state(fn () => ['checked_off_at' => now()]);
    }
}
