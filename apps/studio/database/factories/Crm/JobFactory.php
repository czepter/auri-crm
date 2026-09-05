<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\BookedVia;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'booked_at' => now(),
            'booked_by_id' => User::factory(),
            'booked_via' => BookedVia::Manual,
        ];
    }
}
