<?php

namespace Database\Factories\Integration;

use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'scopes' => [Capability::ManageContacts->value],
            'rate_limit_per_minute' => 60,
        ];
    }

    public function withScopes(Capability ...$capabilities): static
    {
        return $this->state(fn () => ['scopes' => array_map(fn (Capability $c) => $c->value, $capabilities)]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
