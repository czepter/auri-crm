<?php

namespace Database\Factories\Crm;

use App\Crm\Models\InternalNote;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalNote>
 */
class InternalNoteFactory extends Factory
{
    protected $model = InternalNote::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
