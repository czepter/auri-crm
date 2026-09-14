<?php

namespace App\Integration\Data;

use App\Contracts\Data\ContractData;
use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Project;
use App\Payments\Data\PaymentPlanData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * GET /projects/{project}/money: what #402 found missing from the api
 * surface — a project's payment plan and its contracts in one read, so an
 * MCP caller does not have to guess whether a plan exists before offering
 * record_payment or create_payment_plan.
 */
#[TypeScript]
class ProjectMoneyReadData extends Data
{
    /** @param  list<ContractData>  $contracts */
    public function __construct(
        public ?PaymentPlanData $paymentPlan,
        public array $contracts,
    ) {}

    public static function fromModel(Project $project): self
    {
        $project->loadMissing(['job.paymentPlan.installments', 'contracts.template', 'contracts.signers.contact', 'contacts']);

        $roles = $project->contacts->mapWithKeys(
            fn ($contact) => [$contact->id => ProjectContactRole::from($contact->pivot->role)]
        )->all();

        return new self(
            paymentPlan: $project->job?->paymentPlan === null ? null : PaymentPlanData::fromModel($project->job->paymentPlan),
            contracts: $project->contracts
                ->map(fn (Contract $contract) => ContractData::fromModel($contract, "/vertraege/{$contract->public_id}/pdf", $roles))
                ->values()
                ->all(),
        );
    }
}
