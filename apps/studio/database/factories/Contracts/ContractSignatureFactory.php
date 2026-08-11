<?php

namespace Database\Factories\Contracts;

use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractSignature>
 */
class ContractSignatureFactory extends Factory
{
    protected $model = ContractSignature::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'contact_id' => Contact::factory(),
            'typed_name' => fake()->name(),
            'consented_at' => now(),
            'document_sha256' => hash('sha256', fake()->uuid()),
            'contract_template_version' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
        ];
    }
}
