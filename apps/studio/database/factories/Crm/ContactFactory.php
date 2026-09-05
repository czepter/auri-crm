<?php

namespace Database\Factories\Crm;

use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('0171 #######'),
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}
