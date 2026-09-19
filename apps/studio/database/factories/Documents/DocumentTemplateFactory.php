<?php

namespace Database\Factories\Documents;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    public function definition(): array
    {
        return [
            'family_id' => (string) new Cuid2,
            'version' => 1,
            'type' => DocumentTemplateType::Contract,
            'shoot_kind' => null,
            'name' => 'Hochzeit',
            'body' => '<p>Vertrag mit {{ project.name }} ({{ project.type }}), '
                .'vertreten durch {{ contact.name }}, gebucht am {{ job.bookedAt }}.</p>',
            'is_current' => true,
        ];
    }
}
