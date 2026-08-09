<?php

namespace App\Contracts\Http\Controllers;

use App\Contracts\Data\ContractTemplateData;
use App\Contracts\Models\ContractTemplate;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD over the current version of every template family. "Update" never
 * writes to an existing row — see ContractTemplate::newVersion() — so a
 * Contract already rendered from an older version keeps pointing at a row
 * this controller never touches again.
 */
class ContractTemplateController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ContractTemplate::class);

        $templates = ContractTemplate::current()->orderBy('name')->get();

        return Inertia::render('ContractTemplates/Index', [
            'templates' => ContractTemplateData::collect($templates),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ContractTemplate::class);

        return Inertia::render('ContractTemplates/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ContractTemplate::class);

        $validated = $this->validated($request);

        $template = ContractTemplate::createFirstVersion($validated);

        AuditLogger::record(
            event: 'contract_template.created',
            actor: auth('staff')->user(),
            context: ['name' => $template->name],
            subject: $template->public_id,
        );

        return redirect('/vertragsvorlagen');
    }

    public function edit(ContractTemplate $contractTemplate): Response
    {
        $this->authorize('update', $contractTemplate);

        // A superseded version is history, not a draft — reached only by a
        // stale link, since the index only ever lists is_current rows.
        abort_unless($contractTemplate->is_current, 404);

        return Inertia::render('ContractTemplates/Edit', [
            'template' => ContractTemplateData::fromModel($contractTemplate),
        ]);
    }

    public function update(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->authorize('update', $contractTemplate);

        abort_unless($contractTemplate->is_current, 404);

        $validated = $this->validated($request);

        // Never $contractTemplate->update(...): this is the seam that keeps
        // every already-rendered Contract's template row exactly as it was.
        $version = $contractTemplate->newVersion($validated);

        AuditLogger::record(
            event: 'contract_template.updated',
            actor: auth('staff')->user(),
            context: ['name' => $version->name, 'version' => $version->version],
            subject: $version->public_id,
        );

        return redirect('/vertragsvorlagen');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
        ]);
    }
}
