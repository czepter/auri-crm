<?php

namespace Database\Factories\Integration;

use App\Integration\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'url' => fake()->url(),
            'active' => true,
            'subscribed_events' => ['ProjectBooked'],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    public function subscribedTo(string ...$eventTypes): static
    {
        return $this->state(fn () => ['subscribed_events' => $eventTypes]);
    }
}
