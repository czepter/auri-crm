<?php

namespace App\Crm\Actions;

use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Support\CrmVocabulary;
use App\Models\StudioSettings;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Enums\ContractMode;
use Illuminate\Support\Collection;

/**
 * The one thing a project asks for next, for the head every tab carries.
 * `href` navigates; `post` is a state change the head fires itself.
 *
 * @phpstan-type Next array{label: string, href: string|null, post: string|null}
 */
class ReadNextAction
{
    /** @return Next|null */
    public function __invoke(ClientCase $project): ?array
    {
        if ($project->closeLabel() !== null || $project->isCompleted()) {
            return null;
        }

        $id = $project->public_id;

        if (! $project->isBooked()) {
            $quote = $project->quotes()->whereNotIn('status', ['superseded', 'expired'])->latest('id')->first();

            return match (true) {
                $quote === null => ['label' => 'quotes.create', 'href' => "/angebote/neu?projekt={$id}", 'post' => null],
                $quote->status->value === 'draft' => ['label' => 'lead.sendQuote', 'href' => "/angebote/{$quote->public_id}/senden", 'post' => null],
                $quote->status->value === 'sent' => ['label' => 'lead.step.followUp', 'href' => "/angebote/{$quote->public_id}/nachfassen", 'post' => null],
                default => null,
            };
        }

        $base = "/auftraege/{$id}";

        if (StudioSettings::current()->contractMode() === ContractMode::Always && ! $project->contracts()->where('state', '!=', 'superseded')->exists()) {
            return ['label' => 'project.contractSendStep', 'href' => "{$base}/vertrag", 'post' => null];
        }

        if ($step = $this->dueInstallment($project)) {
            return $step;
        }

        if (! $project->formInstances()->exists()) {
            return ['label' => 'project.formSendStep', 'href' => "{$base}/frageboegen", 'post' => null];
        }

        $shoots = $project->shoots()->get()->reject->isCancelled();

        if ($shoots->isEmpty()) {
            return ['label' => 'project.shootCreate', 'href' => "{$base}/shootings", 'post' => null];
        }

        $vocabulary = app(CrmVocabulary::class);
        $done = collect([StageAnchor::Delivered, StageAnchor::Archived])
            ->map(fn (StageAnchor $anchor) => $vocabulary->anchored(StagePipeline::Project, $anchor)?->key)
            ->filter();

        if ($shoots->contains(fn ($shoot) => ! $done->contains($shoot->status->key))) {
            return null;
        }

        if (! $project->galleries()->exists()) {
            return ['label' => 'galleries.create', 'href' => "{$base}/galerien", 'post' => null];
        }

        $plan = $project->paymentPlan;

        if ($plan === null || $plan->total - $plan->installments()->sum('paid_amount') <= 0) {
            return ['label' => 'project.completeMenu', 'href' => null, 'post' => "{$base}/abschluss"];
        }

        return $this->openPayment($project);
    }

    /** @return Next|null */
    private function dueInstallment(ClientCase $project): ?array
    {
        $live = $this->liveInstallments($project);
        $installment = $live->first(fn ($row) => $row->invoice_id === null && $row->status !== PaymentInstallmentStatus::Paid);

        if ($installment === null) {
            return null;
        }

        $index = $live->search($installment);

        if ($index > 0 && $installment->due_on->gt(today()->addDays(14))) {
            return null;
        }

        $label = match (true) {
            $index === 0 => 'project.depositIssueStep',
            $index === $live->count() - 1 => 'project.restIssueStep',
            default => 'project.rateIssueStep',
        };

        return ['label' => $label, 'href' => null, 'post' => "/raten/{$installment->public_id}/rechnung"];
    }

    /** @return Next|null */
    private function openPayment(ClientCase $project): ?array
    {
        $installment = $this->liveInstallments($project)->first(fn ($row) => $row->status !== PaymentInstallmentStatus::Paid);

        return match (true) {
            $installment === null => null,
            $installment->invoice_id === null => ['label' => 'project.restIssueStep', 'href' => null, 'post' => "/raten/{$installment->public_id}/rechnung"],
            default => ['label' => 'project.paymentRecordStep', 'href' => "/rechnungen/{$installment->invoice->public_id}", 'post' => null],
        };
    }

    /** @return Collection<int, PaymentInstallment> */
    private function liveInstallments(ClientCase $project): Collection
    {
        $plan = $project->paymentPlan;

        if ($plan === null || $plan->settled_at !== null || ! auth('staff')->user()?->can('managePaymentPlan', $project)) {
            return collect();
        }

        return $plan->installments()->with('invoice')->whereNull('cancelled_at')->orderBy('sort_order')->get();
    }
}
