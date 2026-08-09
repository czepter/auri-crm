<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormInstanceField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormInstanceField>
 */
class FormInstanceFieldFactory extends Factory
{
    protected $model = FormInstanceField::class;

    public function definition(): array
    {
        return [
            'label' => 'Wer gehört zur engsten Familie?',
            'type' => FormFieldType::Text,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['required' => true]);
    }

    /** @param  list<string>  $options */
    public function select(array $options): static
    {
        return $this->state(fn () => ['type' => FormFieldType::Select, 'options' => $options]);
    }
}
