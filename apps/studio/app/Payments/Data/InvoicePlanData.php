<?php

namespace App\Payments\Data;

use App\Payments\Models\PaymentPlan;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * #479: one plan on /rechnungen. The whole plan rather than flat rows,
 * because editing one rate's amount has to send every rate back — the
 * installments sum to the plan total.
 */
#[TypeScript]
class InvoicePlanData extends Data
{
    public function __construct(
        public string $projectId,
        public string $projectName,
        public ?string $contact,
        public PaymentPlanData $plan,
    ) {}

    public static function fromModel(PaymentPlan $plan): self
    {
        $project = $plan->job->project;

        return new self(
            projectId: $project->public_id,
            projectName: $project->name,
            contact: $project->primaryContact()?->name,
            plan: PaymentPlanData::fromModel($plan),
        );
    }
}
