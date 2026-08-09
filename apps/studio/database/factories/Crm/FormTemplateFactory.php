<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\FormTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory
{
    protected $model = FormTemplate::class;

    public function definition(): array
    {
        return [
            'name' => 'Familienkonstellation',
            'project_type' => ProjectType::Wedding,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
