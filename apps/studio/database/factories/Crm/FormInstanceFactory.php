<?php

namespace Database\Factories\Crm;

use App\Crm\Models\FormInstance;
use App\Crm\Models\Project;
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
            'project_id' => Project::factory(),
            'name' => 'Familienkonstellation',
        ];
    }
}
