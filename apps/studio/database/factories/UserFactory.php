<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => StaffRole::Admin,
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
