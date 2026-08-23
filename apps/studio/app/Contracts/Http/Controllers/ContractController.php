<?php

namespace App\Contracts\Http\Controllers;

use App\Contracts\Data\ContractData;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\ProjectHeaderData;
use App\Crm\Models\Project;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The project's own contract tab: pick a current template, generate a render,
 * see it once it lands. store() dispatches RenderContractJob onto the
 * `render` queue and returns, so requesting a contract stays instant.
 *
 * show() dispatches the same job again when it meets a contract with no
 * bytes, which is what stops one whose render was lost from sitting at
 * "wird erstellt" forever. It stays a dispatch: the tab opens now, the PDF
 * lands on the next look.
 */
class ContractController extends Controller
{
    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['contacts', 'shoots', 'primaryShoot', 'job']);

        return Inertia::render('Projects/Contract', [
            'header' => ProjectHeaderData::fromProject(
                $project,
                (new DeriveProjectPosition)($project),
            ),
            'templates' => DocumentTemplate::current()->ofType(DocumentTemplateType::Contract)
                ->forShootKind($project->primaryShoot?->kind?->value)
                ->orderBy('name')->get()
                ->map(fn (DocumentTemplate $t) => ['value' => $t->public_id, 'label' => "{$t->name} · v{$t->version}"])
                ->values(),
            'contract' => $this->contractData($project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageContracts', $project);

        $validated = $request->validate(['template' => ['required', 'string']]);

        $template = DocumentTemplate::current()->ofType(DocumentTemplateType::Contract)
            ->where('public_id', $validated['template'])->firstOrFail();

        $contract = Contract::create([
            'project_id' => $project->id,
            'contract_template_id' => $template->id,
            'requested_by_id' => auth('staff')->id(),
            'status' => ContractStatus::Pending,
        ]);

        RenderContractJob::dispatch($contract)->onQueue('render');

        AuditLogger::record(
            event: 'contract.render_requested',
            actor: auth('staff')->user(),
            context: ['template' => $template->name, 'version' => $template->version],
            subject: $contract->public_id,
            projectId: $project->id,
        );

        return back();
    }

    /**
     * Serves the artifact RenderContractJob already wrote — nothing here ever
     * touches Gotenberg or the queue. A pending or failed contract has no
     * file yet, so either is a 404 rather than a stand-in PDF.
     *
     * `response()` rather than `download()`: an inline Content-Disposition is
     * what lets the document sit in a bounded `<iframe>` on the screen
     * itself. The "PDF herunterladen" link points at this same URL with an
     * HTML `download` attribute, which saves the file locally regardless.
     */
    public function download(Contract $contract): StreamedResponse
    {
        $this->authorize('view', $contract->project);

        abort_if(! $contract->hasArtifact(), 404);

        return Storage::disk('local')->response($contract->artifact_path, "vertrag-{$contract->public_id}.pdf");
    }

    private function contractData(Project $project): ?ContractData
    {
        $contract = $project->latestContract()->with('template')->first();

        if ($contract === null) {
            return null;
        }

        // rescue(), not a bare call: on a sync queue the render runs here,
        // and Gotenberg being down is a contract that is not there yet, not
        // a project tab that will not open.
        if (! $contract->hasArtifact() && ! $contract->signature()->exists()) {
            rescue(fn () => Bus::dispatch((new RenderContractJob($contract))->onQueue('render')));
            $contract->refresh();
        }

        return ContractData::fromModel($contract, "/vertraege/{$contract->public_id}/pdf");
    }
}
