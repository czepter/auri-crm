<?php

namespace App\Contracts\Actions;

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders a contract's PDF if — and only if — there are no bytes yet.
 *
 * The one place a contract is ever built. RenderContractJob calls it from the
 * queue when a render is requested; both contract screens call it when they
 * are about to show a contract that has none, so a row that says "rendered"
 * without an artifact behind it heals on the next look instead of standing
 * there as "wird erstellt" forever.
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
        private readonly DocumentMergeDataBuilder $mergeData,
        private readonly DocumentRenderer $renderer,
    ) {}

    /**
     * ponytail: a contract whose render keeps failing — a template that
     * cannot resolve, Gotenberg down for a day — pays a Gotenberg call on
     * every look, and every look waits for it. Back the retry off against
     * `error` and `rendered_at` if a broken template ever makes a screen
     * slow enough for anyone to notice.
     */
    public function __invoke(Contract $contract): Contract
    {
        if ($contract->hasArtifact() || $contract->signature()->exists()) {
            return $contract;
        }

        // Two screens opened at once, or a screen racing the queue worker,
        // would otherwise both spend a Gotenberg call on the same contract
        // and both write the same path. Whoever misses the lock returns the
        // row as it stands; the next look picks up what the winner stored.
        $lock = Cache::lock("contract-render:{$contract->id}", 60);

        if (! $lock->get()) {
            return $contract->fresh() ?? $contract;
        }

        try {
            return $this->render($contract->fresh(['project', 'template']) ?? $contract);
        } finally {
            $lock->release();
        }
    }

    private function render(Contract $contract): Contract
    {
        $resolved = MergeFieldResolver::resolve(
            $contract->template->body,
            $this->mergeData->fromProject($contract->project, $this->settings),
        );

        $html = $this->renderer->wrap(DocumentTemplateType::Contract, $resolved, $this->settings);

        try {
            $pdf = $this->gotenberg->htmlToPdf($html);
        } catch (Throwable $e) {
            $contract->update(['status' => ContractStatus::Failed, 'error' => $e->getMessage()]);

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

        return $contract;
    }
}
