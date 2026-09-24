<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'case_id' => ClientCase::factory(),
            'title' => 'Crew-Briefing verschicken',
            'audience' => TaskAudience::Internal,
            'due_on' => now()->addDays(3)->format('Y-m-d'),
        ];
    }

    public function due(?string $date): static
    {
        return $this->state(fn () => ['due_on' => $date]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
