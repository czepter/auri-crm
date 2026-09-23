<?php

namespace Database\Factories\Crm;

use App\Crm\Models\ClientCase;
use App\Crm\Models\FormInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormInstance>
 */
class FormInstanceFactory extends Factory
{
    protected $model = FormInstance::class;

    public function definition(): array
    {
        return [
            'case_id' => ClientCase::factory(),
            'name' => 'Familienkonstellation',
        ];
    }
}
