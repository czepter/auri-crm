<?php

namespace App\Portal\Http\Controllers;

use App\Contracts\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's read of a rendered contract — mockup 07. Reads only the
 * artifact RenderContractJob already stored; nothing here can trigger a
 * render. Signing (the mockup's right-hand rail) is M-02's, so this screen
 * shows only what M-01 actually built: the document, and a way to download
 * it.
 *
 * Asks Contact::visibleProjects() the same question ProjectController does,
 * so a project the contact is not on and one the studio has not made
 * portal_visible fail identically, as a 404 rather than a 403.
 */
class ContractController extends Controller
{
    public function show(string $project): Response
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('projects.public_id', $project)->firstOrFail();
        $contract = $found->latestContract()->with('template')->first();

        return Inertia::render('Contracts/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'contract' => $contract === null ? null : [
                'status' => $contract->status->value,
                'templateName' => $contract->template->name,
                'templateVersion' => $contract->template->version,
                'downloadUrl' => $contract->status === ContractStatus::Rendered
                    ? "/projekte/{$found->public_id}/vertrag/pdf"
                    : null,
            ],
        ]);
    }

    /**
     * Streams the stored PDF, inline — see App\Contracts\Http\Controllers\
     * ContractController::download() for why `response()` rather than
     * `download()`. Never renders — see the class note.
     */
    public function download(string $project): StreamedResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('projects.public_id', $project)->firstOrFail();
        $contract = $found->latestContract()->first();

        abort_if($contract === null || $contract->artifact_path === null, 404);

        return Storage::disk('local')->response($contract->artifact_path, "vertrag-{$contract->public_id}.pdf");
    }
}
