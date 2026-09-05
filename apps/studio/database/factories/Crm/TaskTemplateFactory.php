<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ShootKind;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\TaskTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTemplate>
 */
class TaskTemplateFactory extends Factory
{
    protected $model = TaskTemplate::class;

    public function definition(): array
    {
        return [
            'title' => 'Crew-Briefing verschicken',
            'audience' => TaskAudience::Internal,
            'day_offset' => -1,
            'shoot_kind' => null,
        ];
    }

    public function offset(int $days): static
    {
        return $this->state(fn () => ['day_offset' => $days]);
    }

    public function forKind(?ShootKind $kind): static
    {
        return $this->state(fn () => ['shoot_kind' => $kind]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
