<?php

namespace App\Portal\Http\Controllers;

use App\Contracts\Actions\SignContract;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\MergeFieldResolver;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's read of a rendered contract — mockup 07 — and, since M-02,
 * the signing rail beside it.
 *
 * show() dispatches RenderContractJob when there is no artifact, so a
 * contract whose render was lost is picked up rather than leaving the client
 * at "wird erstellt" with nothing behind it. An artifact that exists is never
 * rebuilt, and a signed contract is never touched at all — sign() hashed
 * those exact bytes.
 *
 * Asks Contact::visibleProjects() the same question CaseController does,
 * so a project the contact is not on and one the studio has not made
 * portal_visible fail identically, as a 404 rather than a 403.
 */
class ContractController extends Controller
{
    public function show(string $case, DocumentMergeData $merge): Response|RedirectResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('cases.public_id', $case)->firstOrFail();
        $contract = $found->latestContract()->with('signature', 'template')->first();

        if ($contract === null) {
            return redirect("/projekte/{$found->public_id}");
        }

        // rescue(), not a bare call: on a sync queue the render runs here,
        // and Gotenberg being down is a contract the client cannot read yet,
        // not a screen they cannot open.
        if ($contract !== null && ! $contract->hasArtifact() && ! $contract->signature()->exists()) {
            rescue(fn () => Bus::dispatch((new RenderContractJob($contract))->onQueue('render')));
            $contract->refresh();
        }

        return Inertia::render('Contracts/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            // T-08's own switch (default off): the affordance half of "sign
            // contracts", the sign() route below is the authorisation half
            // via `portal.capability:sign_contracts`. Never hides an
            // existing signature — this only gates the form, not the record.
            'canSign' => (bool) StudioSettings::current()->portal_sign_contracts_enabled,
            'contract' => $contract === null ? null : [
                'status' => $contract->status->value,
                'downloadUrl' => $contract->status === ContractStatus::Rendered && $contract->hasArtifact()
                    ? "/projekte/{$found->public_id}/vertrag/pdf"
                    : null,
                // Null until SignContract has written the one row a Contract
                // will ever have — the rail shows the form or the confirmation
                // off this alone, never off a separate "can sign" flag.
                // The text the PDF is made from, readable on a phone where an
                // embedded PDF is a blank frame. A signed contract keeps its
                // stored bytes: the live text may have moved on since.
                'html' => $contract->signature === null
                    ? MergeFieldResolver::resolve($contract->resolvedBody(), $merge->forCase($found), escape: true)
                    : null,
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
    public function sign(Request $request, string $case, SignContract $sign, DocumentMergeData $merge): RedirectResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('cases.public_id', $case)->firstOrFail();
        $contract = $found->latestContract()->with('template')->first();

        abort_if($contract === null, 404);

        $validated = $request->validate([
            'typedName' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
        ]);

        $shown = $contract->status === ContractStatus::Rendered && $contract->hasArtifact()
            ? null
            : MergeFieldResolver::resolve($contract->resolvedBody(), $merge->forCase($found), escape: true);

        $sign($contract, $contact, $validated['typedName'], (string) $request->ip(), $request->userAgent(), $shown);

        return back();
    }

    /**
     * Streams the stored PDF, inline — see App\Contracts\Http\Controllers\
     * ContractController::download() for why `response()` rather than
     * `download()`. Never renders — see the class note.
     */
    public function download(string $case): StreamedResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('cases.public_id', $case)->firstOrFail();
        $contract = $found->latestContract()->first();

        abort_if($contract === null || ! $contract->hasArtifact(), 404);

        return Storage::disk('local')->response($contract->artifact_path, "vertrag-{$contract->public_id}.pdf");
    }
}
