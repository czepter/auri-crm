<?php

namespace Database\Factories\Engagement;

use App\Crm\Models\Contact;
use App\Engagement\Models\Thread;
use App\Engagement\Models\ThreadParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreadParticipant>
 */
class ThreadParticipantFactory extends Factory
{
    protected $model = ThreadParticipant::class;

    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'contact_id' => Contact::factory(),
            'user_id' => null,
        ];
    }

    public function staff(?User $user = null): static
    {
        return $this->state(fn () => [
            'contact_id' => null,
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }
}
