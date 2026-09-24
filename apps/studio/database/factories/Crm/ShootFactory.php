<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
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
            'case_id' => ClientCase::factory(),
            'kind' => 'Portrait',
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

    /** Places a shoot mid-pipeline, optionally stale, without transitioning it. */
    public function atStatus(ShootStatus|Stage|string $status, int $daysAgo = 0): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'status_entered_at' => now()->subDays($daysAgo),
        ]);
    }

    public function cancelled(string $reason): static
    {
        return $this->state(fn () => [
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);
    }
}
