<?php

namespace Database\Factories\Contracts;

use App\Contracts\Enums\ContractState;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Models\Project;
use App\Documents\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'contract_template_id' => DocumentTemplate::factory(),
            'status' => ContractStatus::Pending,
            // Ein Vertrag im Test ist einer, der beim Kunden liegt. Der
            // Entwurf ist seit den Fassungen der Sonderfall und wird
            // ausdrücklich erzeugt.
            'state' => ContractState::Valid,
            'sent_at' => now(),
            'version' => fn (array $attributes) => is_numeric($attributes['project_id'] ?? null)
                ? (Contract::query()->where('project_id', $attributes['project_id'])->max('version') ?? 0) + 1
                : 1,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'state' => ContractState::Draft,
            'sent_at' => null,
        ]);
    }

    public function rendered(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatus::Rendered,
            'artifact_path' => 'contracts/test/'.fake()->uuid().'.pdf',
            'rendered_at' => now(),
        ]);
    }
}
