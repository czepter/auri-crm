<?php

namespace App\Crm\Actions;

use App\Contracts\Enums\ContractState;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Support\CrmVocabulary;
use App\Quotes\Enums\QuoteStatus;

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
            $quote = $project->quotes()->whereNotIn('status', [QuoteStatus::Superseded, QuoteStatus::Expired])->latest('id')->first();

            return match (true) {
                $quote === null => ['label' => 'quotes.create', 'href' => "/angebote/neu?projekt={$id}", 'post' => null],
                $quote->status === QuoteStatus::Draft => ['label' => 'lead.sendQuote', 'href' => "/angebote/{$quote->public_id}/senden", 'post' => null],
                $quote->status === QuoteStatus::Sent => ['label' => 'lead.step.followUp', 'href' => "/anfragen/{$id}/nachrichten", 'post' => null],
                default => null,
            };
        }

        $base = "/auftraege/{$id}";

        if (! $project->contracts()->where('state', '!=', ContractState::Superseded)->exists()) {
            return ['label' => 'project.contractSendStep', 'href' => "{$base}/vertrag", 'post' => null];
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
        $paid = $plan === null || $plan->total - $plan->installments()->sum('paid_amount') <= 0;

        return $paid ? ['label' => 'project.completeMenu', 'href' => null, 'post' => "{$base}/abschluss"] : null;
    }
}
