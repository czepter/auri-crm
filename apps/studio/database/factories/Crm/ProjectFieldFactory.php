<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ProjectFieldType;
use App\Crm\Models\ProjectField;
use App\Crm\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectField>
 */
class ProjectFieldFactory extends Factory
{
    protected $model = ProjectField::class;

    public function definition(): array
    {
        return [
            'project_type_id' => ProjectType::query()->where('key', 'wedding')->value('id')
                ?? ProjectType::factory(),
            'key' => 'gaeste',
            'label' => 'Gäste',
            'type' => ProjectFieldType::Number,
            'unit' => 'Personen',
        ];
    }

    public function ofType(ProjectFieldType $type, array $options = []): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'unit' => null,
            'options' => $options ?: null,
        ]);
    }
}
