<?php

namespace App\Integration\Data;

use App\Crm\Models\Project;
use App\Payments\Data\PaymentPlanData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * GET /projects/{project}/money: what #402 found missing from the api
 * surface — a project's payment plan, so an
 * MCP caller does not have to guess whether a plan exists before offering
 * record_payment or create_payment_plan.
 */
#[TypeScript]
class ProjectMoneyReadData extends Data
{
    public function __construct(
        public ?PaymentPlanData $paymentPlan,
    ) {}

    public static function fromModel(Project $project): self
    {
        $project->loadMissing('paymentPlan.installments');

        return new self(
            paymentPlan: $project->paymentPlan === null ? null : PaymentPlanData::fromModel($project->paymentPlan),
        );
    }
}
