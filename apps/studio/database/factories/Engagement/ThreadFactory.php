<?php

namespace Database\Factories\Engagement;

use App\Crm\Models\Project;
use App\Engagement\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thread>
 */
class ThreadFactory extends Factory
{
    protected $model = Thread::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'subject' => fake()->sentence(4),
        ];
    }
}
