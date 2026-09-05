<?php

namespace Database\Factories\Crm;

use App\Crm\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectType>
 */
class ProjectTypeFactory extends Factory
{
    protected $model = ProjectType::class;

    public function definition(): array
    {
        return [
            'key' => 'elopement',
            'name' => 'Elopement',
            'sort_order' => 90,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
