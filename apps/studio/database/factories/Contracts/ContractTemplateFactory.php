<?php

namespace Database\Factories\Contracts;

use App\Contracts\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    protected $model = ContractTemplate::class;

    public function definition(): array
    {
        return [
            'family_id' => (string) new Cuid2,
            'version' => 1,
            'name' => 'Hochzeit',
            'body' => '<p>Vertrag mit {{ project.name }} ({{ project.type }}), '
                .'vertreten durch {{ contact.name }}, gebucht am {{ job.bookedAt }}.</p>',
            'is_current' => true,
        ];
    }
}
