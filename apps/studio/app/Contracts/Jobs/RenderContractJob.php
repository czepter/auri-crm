<?php

namespace App\Contracts\Jobs;

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Merge fields resolve here, at the moment this job runs — never live from a
 * controller. Everything the template's body can reference is pulled off the
 * project fresh, right before it is baked into the HTML sent to Gotenberg, so
 * a contract read after signing can never silently change because the
 * project it was built from moved on.
 *
 * The array itself comes from App\Documents\Support\DocumentMergeDataBuilder
 * (T-12) — the one place every document type builds its merge data from, so
 * `{{ project.name }}` means the same thing here as it will wherever a quote
 * or a transactional mail eventually renders. The PDF shell (letterhead and
 * footer) comes from App\Documents\Support\DocumentRenderer, shared rather
 * than built here.
 *
 * Runs on the `render` queue (config/horizon.php's supervisor-render, M-00),
 * which is why the caller only ever dispatches this and returns — never waits
 * on it.
 */
class RenderContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Contract $contract) {}

    public function handle(
        GotenbergClient $gotenberg,
        StudioSettingsResolver $settings,
        DocumentMergeDataBuilder $mergeData,
        DocumentRenderer $renderer,
    ): void {
        $contract = $this->contract->fresh(['project', 'template']);

        // Deleted since dispatch, or already handled by an earlier attempt —
        // re-rendering an already-rendered contract, and overwriting an
        // artifact a client may already have, is exactly what this guards
        // against.
        if ($contract === null || $contract->status !== ContractStatus::Pending) {
            return;
        }

        $resolved = MergeFieldResolver::resolve(
            $contract->template->body,
            $mergeData->fromProject($contract->project, $settings),
        );

        $html = $renderer->wrap(DocumentTemplateType::Contract, $resolved, $settings);

        try {
            $pdf = $gotenberg->htmlToPdf($html);
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
        ]);
    }
}
