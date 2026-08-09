<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => fake()->lastName().' — Portrait',
            'type' => ProjectType::Portrait,
        ];
    }

    public function wedding(): static
    {
        return $this->state(fn () => [
            'name' => fake()->lastName().' & '.fake()->lastName().' — Hochzeit',
            'type' => ProjectType::Wedding,
        ]);
    }
}
