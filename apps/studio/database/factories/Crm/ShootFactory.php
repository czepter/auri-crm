<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shoot>
 */
class ShootFactory extends Factory
{
    protected $model = Shoot::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'kind' => ShootKind::Portrait,
            'date' => fake()->dateTimeBetween('+1 month', '+18 months')->format('Y-m-d'),
            'status' => ShootStatus::Requested,
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function on(?string $date): static
    {
        return $this->state(fn () => ['date' => $date]);
    }
}
