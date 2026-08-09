<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\CrewRole;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrewAssignment>
 */
class CrewAssignmentFactory extends Factory
{
    protected $model = CrewAssignment::class;

    public function definition(): array
    {
        return [
            'shoot_id' => Shoot::factory(),
            'user_id' => User::factory(),
            'role' => CrewRole::LeadPhotographer,
        ];
    }
}
