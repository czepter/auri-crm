<?php

namespace App\Contracts\Http\Controllers;

use App\Contracts\Data\ContractData;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractTemplate;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\ProjectHeaderData;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The project's own contract tab: pick a current template, generate a render,
 * see it once it lands. Never renders inline — store() only ever dispatches
 * RenderContractJob onto the `render` queue and returns; the screen finds out
 * a render finished the same way every Inertia screen finds out anything
 * changed, by asking again.
 */
class ContractController extends Controller
{
    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Contract', [
            'header' => ProjectHeaderData::fromProject(
                $project->load(['contacts', 'shoots', 'primaryShoot', 'job']),
                (new DeriveProjectPosition)($project),
            ),
            'templates' => ContractTemplate::current()->orderBy('name')->get()
                ->map(fn (ContractTemplate $t) => ['value' => $t->public_id, 'label' => "{$t->name} · v{$t->version}"])
                ->values(),
            'contract' => $this->contractData($project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageContracts', $project);

        $validated = $request->validate(['template' => ['required', 'string']]);

        $template = ContractTemplate::current()->where('public_id', $validated['template'])->firstOrFail();

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

        abort_if($contract->artifact_path === null, 404);

        return Storage::disk('local')->response($contract->artifact_path, "vertrag-{$contract->public_id}.pdf");
    }

    private function contractData(Project $project): ?ContractData
    {
        $contract = $project->latestContract()->with('template')->first();

        if ($contract === null) {
            return null;
        }

        return ContractData::fromModel($contract, "/vertraege/{$contract->public_id}/pdf");
    }
}
