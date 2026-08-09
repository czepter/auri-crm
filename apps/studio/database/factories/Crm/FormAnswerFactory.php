<?php

namespace Database\Factories\Crm;

use App\Crm\Models\FormAnswer;
use App\Crm\Models\FormInstanceField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormAnswer>
 */
class FormAnswerFactory extends Factory
{
    protected $model = FormAnswer::class;

    public function definition(): array
    {
        return [
            'form_instance_field_id' => FormInstanceField::factory(),
            'version' => 1,
            'value' => fake()->sentence(),
        ];
    }
}
