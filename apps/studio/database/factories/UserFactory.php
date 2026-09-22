<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => \App\Enums\StaffRole::Admin,
        ];
    }

    public function withMfa(): static
    {
        return $this->state(fn () => [
            'mfa_secret' => 'ABCDEFGHIJKLMNOP',
            'mfa_confirmed_at' => now(),
            'mfa_recovery_codes' => ['code-one', 'code-two'],
        ]);
    }
}
