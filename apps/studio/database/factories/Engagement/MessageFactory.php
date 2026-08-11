<?php

namespace Database\Factories\Engagement;

use App\Crm\Models\Contact;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /** A client's own message by default — the case that makes this a channel. */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'contact_id' => Contact::factory(),
            'author_id' => null,
            'body' => fake()->paragraph(),
        ];
    }

    public function fromStaff(?User $author = null): static
    {
        return $this->state(fn () => [
            'contact_id' => null,
            'author_id' => $author?->id ?? User::factory(),
        ]);
    }
}
