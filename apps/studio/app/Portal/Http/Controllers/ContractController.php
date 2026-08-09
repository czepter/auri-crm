<?php

namespace App\Portal\Http\Controllers;

use App\Contracts\Actions\SignContract;
use App\Contracts\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's read of a rendered contract — mockup 07 — and, since M-02,
 * the signing rail beside it. Reads only the artifact RenderContractJob
 * already stored; nothing here can trigger a render, and sign() never
 * touches the artifact except to hash the bytes already sitting in storage.
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
        $contract = $found->latestContract()->with(['template', 'signature'])->first();

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
                // Null until SignContract has written the one row a Contract
                // will ever have — the rail shows the form or the confirmation
                // off this alone, never off a separate "can sign" flag.
                'signature' => $contract->signature === null ? null : [
                    'typedName' => $contract->signature->typed_name,
                    'consentedAt' => $contract->signature->consented_at->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * The signing rail's submit. Consent is enforced here, not just in the
     * form's disabled state — an `accepted` rule rejects anything but an
     * explicit true, so a request built by hand without it fails exactly
     * like an unchecked box would.
     */
    public function sign(Request $request, string $project, SignContract $sign): RedirectResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('projects.public_id', $project)->firstOrFail();
        $contract = $found->latestContract()->with('template')->first();

        abort_if($contract === null, 404);

        $validated = $request->validate([
            'typedName' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
        ]);

        $sign($contract, $contact, $validated['typedName'], (string) $request->ip(), $request->userAgent());

        return back();
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
