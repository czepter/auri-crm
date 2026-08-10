<?php

namespace Database\Factories\Integration;

use App\Integration\Enums\WebhookDeliveryStatus;
use App\Integration\Models\WebhookDelivery;
use App\Integration\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event_type' => 'ProjectBooked',
            'payload' => ['project' => fake()->uuid()],
            'attempts' => 0,
            'status' => WebhookDeliveryStatus::Pending,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::Delivered,
            'response_status' => 200,
            'attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::Failed,
            'response_status' => 500,
            'attempts' => 5,
        ]);
    }
}
