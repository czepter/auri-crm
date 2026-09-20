<?php

namespace App\Contracts\Http\Controllers;

use App\Contracts\Data\ContractData;
use App\Contracts\Enums\ContractState;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Contracts\Notifications\ContractSent;
use App\Contracts\Support\ClauseCatalog;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\ProjectHeaderData;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Project;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentBlockCatalog;
use App\Documents\Support\InlineHtmlSanitizer;
use App\Documents\Support\MergeFieldVocabulary;
use App\Documents\Support\SafeHtml;
use App\Documents\Support\StarterBlockSerializer;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationService;
use App\Notifications\Support\MailPreview;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The project's own contract tab: pick a template, edit the wording of the
 * current Fassung in the block editor, send it.
 *
 * A contract is a series of Fassungen, not one render. update() writes into
 * a draft and starts a new Fassung against anything else — the same seam
 * DocumentTemplate::newVersion() is, for the same reason: the Fassung a
 * client signed must keep its wording and its bytes. Which Fassung counts is
 * decided by send() alone (Contract::markSent()), so the signed one stays
 * valid until the replacement actually goes out.
 *
 * show() dispatches RenderContractJob again when it meets a contract with no
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

        $versions = $this->versions($project);
        $current = $versions->first();

        if ($current !== null) {
            $this->ensureRendered($current);
        }

        return Inertia::render('Projects/Contract', [
            'header' => ProjectHeaderData::fromProject(
                $project,
                (new DeriveProjectPosition)($project),
            ),
            'templates' => DocumentTemplate::current()->ofType(DocumentTemplateType::Contract)
                ->forShootKind($project->primaryShoot?->kind)
                ->orderBy('name')->get()
                ->map(fn (DocumentTemplate $t) => ['value' => $t->public_id, 'label' => "{$t->name} · v{$t->version}"])
                ->values(),
            'contract' => $current === null ? null : $this->contractData($current, $project),
            'versions' => $versions->map(fn (Contract $c) => $this->contractData($c, $project))->values(),
            // Only contacts who are not already on the current Fassung — the
            // add-signer select offers what can still be added, nothing else.
            'contacts' => $project->contacts
                ->reject(fn ($contact) => $current?->signers->contains('contact_id', $contact->id) ?? false)
                ->map(fn ($contact) => ['value' => $contact->public_id, 'label' => $contact->name])
                ->values(),
            'palette' => DocumentBlockCatalog::paletteFor(DocumentTemplateType::Contract),
            'clauses' => ClauseCatalog::forTemplate(
                DocumentTemplate::current()->ofType(DocumentTemplateType::Contract)
                    ->forShootKind($project->primaryShoot?->kind)
                    ->orderBy('name')->first(),
            ),
            'mergeFields' => collect(MergeFieldVocabulary::samples())
                ->map(fn (string $sample, string $path) => ['path' => $path, 'sample' => $sample])
                ->values()->all(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageContracts', $project);

        $validated = $request->validate([
            'template' => ['required', 'string'],
            'clauses' => ['nullable', 'array'],
            'clauses.*.title' => ['required_with:clauses', 'string', 'max:200'],
            'clauses.*.text' => ['required_with:clauses', 'string', 'max:10000'],
        ]);

        $template = DocumentTemplate::current()->ofType(DocumentTemplateType::Contract)
            ->where('public_id', $validated['template'])->firstOrFail();

        $blocks = isset($validated['clauses'])
            ? ['blocks' => array_map(
                fn (array $clause) => ['type' => 'clause', 'data' => ['title' => $clause['title'], 'text' => $clause['text']]],
                array_values($validated['clauses']),
            )]
            : null;

        $contract = Contract::create([
            'project_id' => $project->id,
            'contract_template_id' => $template->id,
            'requested_by_id' => auth('staff')->id(),
            'status' => ContractStatus::Pending,
            'state' => ContractState::Draft,
            'version' => $this->nextVersion($project),
            // Der Schnellbau schreibt eine eigene Fassung; ohne ihn bleibt die
            // Vorlage der Wortlaut (Contract::resolvedBody()).
            'body' => $blocks === null ? null : StarterBlockSerializer::toHtml($blocks),
            'blocks' => $blocks,
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
     * "Fassung sichern". A draft is edited in place; anything else — sent,
     * signed, superseded — is left exactly as it is and the edit becomes the
     * next Fassung, carrying the same signers with their signatures cleared,
     * because a new wording is a new thing to sign.
     */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('manageContracts', $contract->project);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:100000', function (string $attribute, mixed $value, Closure $fail) {
                if (SafeHtml::hasUnsafeHref((string) $value)) {
                    $fail('Links dürfen nur auf http, https oder mailto zeigen.');
                }
            }],
            'blocks' => ['nullable', 'array'],
            'blocks.blocks' => ['nullable', 'array'],
            // Without this rule validated() keeps every block's type and drops
            // its content — see DocumentTemplateController::update().
            'blocks.blocks.*.type' => ['required_with:blocks.blocks', 'string'],
            'blocks.blocks.*.data' => ['nullable', 'array'],
        ]);

        $blocks = isset($validated['blocks']) && is_array($validated['blocks'])
            ? InlineHtmlSanitizer::blocks($validated['blocks'])
            : null;

        $saved = $contract->state === ContractState::Draft
            ? tap($contract)->update([
                'body' => $validated['body'],
                'blocks' => $blocks,
                'status' => ContractStatus::Pending,
                'artifact_path' => null,
                'rendered_at' => null,
            ])
            : $this->nextFassung($contract, $validated['body'], $blocks);

        RenderContractJob::dispatch($saved)->onQueue('render');

        AuditLogger::record(
            event: 'contract.version_saved',
            actor: auth('staff')->user(),
            context: ['version' => $saved->version],
            subject: $saved->public_id,
            projectId: $saved->project_id,
        );

        return back();
    }

    public function compose(Contract $contract, StudioSettingsResolver $settings): Response
    {
        $this->authorize('manageContracts', $contract->project);

        abort_unless($contract->state === ContractState::Draft, 404);

        $recipients = $this->recipients($contract);

        return Inertia::render('Contracts/Send', [
            'projectId' => $contract->project->public_id,
            'contractId' => $contract->public_id,
            'recipients' => $recipients
                ->map(fn ($contact) => ['name' => $contact->name, 'email' => $contact->email])
                ->values(),
            'mailHtml' => MailPreview::html(new ContractSent($contract, '', MailPreview::MESSAGE, $settings), $recipients->first() ?? (object) []),
        ]);
    }

    /**
     * Sending is what makes a Fassung the valid one — see Contract::markSent().
     * Mails every signer only when the compose screen posted a subject; a
     * Fassung without signers is sent straight from the tab's button.
     */
    public function send(Request $request, Contract $contract, NotificationService $notifications, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorize('manageContracts', $contract->project);

        abort_unless($contract->state === ContractState::Draft, 422);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $contract->markSent();

        if (! $contract->hasArtifact()) {
            RenderContractJob::dispatch($contract)->onQueue('render');
        }

        AuditLogger::record(
            event: 'contract.sent',
            actor: auth('staff')->user(),
            context: ['version' => $contract->version],
            subject: $contract->public_id,
            projectId: $contract->project_id,
        );

        if (blank($validated['subject'] ?? null)) {
            return back();
        }

        foreach ($this->recipients($contract) as $contact) {
            $notifications->send($contact, new ContractSent($contract, $validated['subject'], $validated['message'] ?? null, $settings));
        }

        return redirect("/auftraege/{$contract->project->public_id}/vertrag");
    }

    private function recipients(Contract $contract): Collection
    {
        return $contract->signers()->with('contact')->get()
            ->pluck('contact')
            ->filter(fn ($contact) => filled($contact?->email));
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

    /** @return Collection<int, Contract> newest Fassung first */
    private function versions(Project $project)
    {
        return Contract::query()
            ->where('project_id', $project->id)
            ->with(['template', 'signers.contact'])
            ->orderByDesc('version')
            ->get();
    }

    private function nextVersion(Project $project): int
    {
        return 1 + (int) Contract::query()->where('project_id', $project->id)->max('version');
    }

    /**
     * @param  array<string, mixed>|null  $blocks
     */
    private function nextFassung(Contract $contract, string $body, ?array $blocks): Contract
    {
        return DB::transaction(function () use ($contract, $body, $blocks) {
            $next = Contract::create([
                'project_id' => $contract->project_id,
                'contract_template_id' => $contract->contract_template_id,
                'requested_by_id' => auth('staff')->id(),
                'status' => ContractStatus::Pending,
                'state' => ContractState::Draft,
                'version' => $this->nextVersion($contract->project),
                'body' => $body,
                'blocks' => $blocks,
            ]);

            foreach ($contract->signers as $signer) {
                $next->signers()->create(['contact_id' => $signer->contact_id]);
            }

            return $next;
        });
    }

    /**
     * rescue(), not a bare call: on a sync queue the render runs here, and
     * Gotenberg being down is a contract that is not there yet, not a project
     * tab that will not open.
     */
    private function ensureRendered(Contract $contract): void
    {
        if ($contract->hasArtifact() || $contract->signature()->exists()) {
            return;
        }

        rescue(fn () => Bus::dispatch((new RenderContractJob($contract))->onQueue('render')));
        $contract->refresh();
    }

    /** E-16: die Frist, an der die Automationsregel hängt. */
    public function deadline(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('manageContracts', $contract->project);

        $contract->update($request->validate([
            'sign_deadline_on' => ['nullable', 'date'],
        ]));

        return back();
    }

    private function contractData(Contract $contract, Project $project): ContractData
    {
        return ContractData::fromModel(
            $contract,
            "/vertraege/{$contract->public_id}/pdf",
            $project->contacts->mapWithKeys(
                fn ($contact) => [$contact->id => ProjectContactRole::from($contact->pivot->role)]
            )->all(),
        );
    }
}
