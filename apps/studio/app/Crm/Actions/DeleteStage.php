<?php

namespace App\Crm\Actions;

use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Support\CrmVocabulary;
use App\Models\User;
use App\Support\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a stage is a data migration: every record standing on it needs
 * somewhere to stand. The target is the studio's choice and never a guess —
 * moving a shoot to the wrong status silently rewrites what the client is
 * told about their pictures.
 *
 * Two deletions are refused outright. An anchored stage carries behaviour the
 * app branches on (StageAnchor), and dropping it would leave a funnel with no
 * position that books a project; a studio may rename or recolour it instead.
 * The last stage of a pipeline is refused because the records have nowhere
 * left to go.
 */
class DeleteStage
{
    public function __construct(private readonly CrmVocabulary $vocabulary) {}

    public function __invoke(Stage $stage, ?Stage $moveTo = null, ?User $actor = null): void
    {
        if ($stage->anchor !== null) {
            throw new DomainException("Stage {$stage->key} carries the {$stage->anchor->value} anchor and cannot be deleted.");
        }

        $standing = $this->countOn($stage);

        if ($standing > 0 && $moveTo === null) {
            throw new DomainException("Stage {$stage->key} still holds {$standing} records and needs a target.");
        }

        if ($moveTo !== null && ($moveTo->pipeline !== $stage->pipeline || $moveTo->is($stage))) {
            throw new DomainException("Stage {$moveTo->key} is not a target for {$stage->key}.");
        }

        DB::transaction(function () use ($stage, $moveTo, $actor, $standing) {
            if ($moveTo !== null && $standing > 0) {
                $this->move($stage, $moveTo);
            }

            AuditLogger::record(
                event: 'stage.deleted',
                actor: $actor ?? auth('staff')->user(),
                context: ['stage' => $stage->key, 'moved' => $standing, 'to' => $moveTo?->key],
                subject: $stage->public_id,
            );

            $stage->delete();
        });

        $this->vocabulary->forget();
    }

    private function countOn(Stage $stage): int
    {
        return $stage->pipeline === StagePipeline::Lead
            ? Lead::query()->where('stage', $stage->key)->count()
            : Shoot::query()->where('status', $stage->key)->count();
    }

    /**
     * A column write rather than AdvanceLeadStage: the records are not
     * advancing, they are being carried along by a rename of the map. Routing
     * them through the action would stamp an entry time and an audit row on
     * every one of them, and the transition rules would reject the move
     * backwards for half of them.
     */
    private function move(Stage $stage, Stage $target): void
    {
        $stage->pipeline === StagePipeline::Lead
            ? Lead::query()->where('stage', $stage->key)->update(['stage' => $target->key])
            : Shoot::query()->where('status', $stage->key)->update(['status' => $target->key]);
    }
}
