<?php

namespace App\Contracts\Jobs;

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Contracts\Support\GotenbergClient;
use App\Contracts\Support\MergeFieldResolver;
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
 * Runs on the `render` queue (config/horizon.php's supervisor-render, M-00),
 * which is why the caller only ever dispatches this and returns — never waits
 * on it.
 */
class RenderContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Contract $contract) {}

    public function handle(GotenbergClient $gotenberg): void
    {
        $contract = $this->contract->fresh(['project', 'template']);

        // Deleted since dispatch, or already handled by an earlier attempt —
        // re-rendering an already-rendered contract, and overwriting an
        // artifact a client may already have, is exactly what this guards
        // against.
        if ($contract === null || $contract->status !== ContractStatus::Pending) {
            return;
        }

        $html = $this->wrapDocument(
            MergeFieldResolver::resolve($contract->template->body, $this->mergeData($contract))
        );

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

    /**
     * Assembled fresh from the database, right now — this is the "at render
     * time" half of M-01's rule. Add a path here and document it in
     * docs/contracts.md; MergeFieldResolver reads dot-paths against exactly
     * this shape.
     *
     * @return array<string, mixed>
     */
    private function mergeData(Contract $contract): array
    {
        $project = $contract->project;
        $contact = $project->primaryContact();

        return [
            'project' => [
                'name' => $project->name,
                'type' => $project->type->label(),
            ],
            'contact' => [
                'name' => $contact?->name ?? '',
                'email' => $contact?->email ?? '',
            ],
            'job' => [
                'bookedAt' => $project->job?->booked_at?->format('d.m.Y') ?? '',
            ],
        ];
    }

    /** Gotenberg's chromium route wants a full document, not a fragment. */
    private function wrapDocument(string $html): string
    {
        if (str_contains($html, '<html')) {
            return $html;
        }

        return '<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';
    }
}
