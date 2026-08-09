<?php

namespace Database\Factories\Contracts;

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
        ];
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
