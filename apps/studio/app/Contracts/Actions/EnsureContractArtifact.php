<?php

namespace App\Contracts\Actions;

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use App\Support\LiveUpdate;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders a contract's PDF if — and only if — there are no bytes yet.
 *
 * The one place a contract is ever built, and only ever from
 * RenderContractJob. A contract screen that finds no bytes dispatches that
 * job, so a row that says "rendered" without an artifact behind it heals
 * instead of standing at "wird erstellt" forever — but it heals on the
 * `render` queue, never while a request waits on Gotenberg.
 *
 * Merge fields resolve here rather than in a controller, which is what makes
 * the artifact a snapshot: everything the template references is pulled off
 * the project at this moment and baked into the bytes.
 *
 * Which is also why a contract with a signature is left alone even when its
 * bytes are gone. SignContract stores a sha256 of what it read; a fresh
 * render of a project that has moved on since would produce different bytes,
 * and a signature pointing at a document that changed under it is not a
 * signature. That case needs a person, not a retry.
 */
class EnsureContractArtifact
{
    public function __construct(
        private readonly GotenbergClient $gotenberg,
        private readonly StudioSettingsResolver $settings,
        private readonly DocumentMergeData $mergeData,
        private readonly DocumentRenderer $renderer,
    ) {}

    /**
     * ponytail: two jobs for the same contract each spend a Gotenberg call
     * and both write the same path — last one wins, which is the same bytes.
     * Take a Cache::lock on the contract id if the duplicate calls ever cost
     * more than the lock would.
     */
    public function __invoke(Contract $contract): Contract
    {
        if ($contract->hasArtifact() || $contract->signature()->exists()) {
            return $contract;
        }

        return $this->render($contract->fresh(['case', 'template']) ?? $contract);
    }

    private function render(Contract $contract): Contract
    {
        $resolved = MergeFieldResolver::resolve(
            $contract->resolvedBody(),
            $this->mergeData->forCase($contract->case),
        );

        $html = $this->renderer->wrap(DocumentTemplateType::Contract, $resolved, $this->settings);

        try {
            $pdf = $this->gotenberg->htmlToPdf($html);
        } catch (Throwable $e) {
            $contract->update(['status' => ContractStatus::Failed, 'error' => $e->getMessage()]);
            LiveUpdate::publish();

            throw $e;
        }

        $path = "contracts/{$contract->tenant_id}/{$contract->public_id}.pdf";
        Storage::disk('local')->put($path, $pdf);

        $contract->update([
            'status' => ContractStatus::Rendered,
            'artifact_path' => $path,
            'rendered_at' => now(),
            'error' => null,
        ]);

        LiveUpdate::publish();

        return $contract;
    }
}
