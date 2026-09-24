<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\BookedVia;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\ClientCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCase>
 */
class ClientCaseFactory extends Factory
{
    protected $model = ClientCase::class;

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

    public function booked(): static
    {
        return $this->state(fn () => [
            'booked_at' => now(),
            'booked_by_id' => User::factory(),
            'booked_via' => BookedVia::Manual,
        ]);
    }
}
