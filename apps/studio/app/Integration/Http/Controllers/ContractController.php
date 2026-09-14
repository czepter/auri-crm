<?php

namespace App\Integration\Http\Controllers;

use App\Contracts\Data\ContractData;
use App\Contracts\Enums\ContractState;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Project;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\SafeHtml;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The api surface's mirror of App\Contracts\Http\Controllers\
 * ContractController — same model writes and RenderContractJob dispatch,
 * `scope:` in place of the staff Policy (see QuoteController's own doc
 * comment on why no $this->authorize() call appears here). Block-editor
 * fields (`blocks`) stay out: a caller here sends wording, not the studio's
 * own drag-and-drop palette.
 */
class ContractController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate(['template' => ['required', 'string']]);

        $template = DocumentTemplate::current()->ofType(DocumentTemplateType::Contract)
            ->where('public_id', $validated['template'])->firstOrFail();

        $contract = Contract::create([
            'project_id' => $project->id,
            'contract_template_id' => $template->id,
            'requested_by_id' => $this->actor($request)?->id,
            'status' => ContractStatus::Pending,
            'state' => ContractState::Draft,
            'version' => 1 + (int) Contract::query()->where('project_id', $project->id)->max('version'),
        ]);

        RenderContractJob::dispatch($contract)->onQueue('render');

        AuditLogger::record(
            event: 'contract.render_requested',
            actor: $this->actor($request),
            context: ['template' => $template->name, 'version' => $template->version],
            subject: $contract->public_id,
            projectId: $project->id,
        );

        return response()->json(['data' => $this->contractData($contract->fresh(['template', 'signers.contact']))], 201);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:100000', function (string $attribute, mixed $value, Closure $fail) {
                if (SafeHtml::hasUnsafeHref((string) $value)) {
                    $fail('Links dürfen nur auf http, https oder mailto zeigen.');
                }
            }],
        ]);

        $contract->update([
            'body' => $validated['body'],
            'status' => ContractStatus::Pending,
            'artifact_path' => null,
            'rendered_at' => null,
        ]);

        RenderContractJob::dispatch($contract)->onQueue('render');

        AuditLogger::record(
            event: 'contract.version_saved',
            actor: $this->actor($request),
            context: ['version' => $contract->version],
            subject: $contract->public_id,
            projectId: $contract->project_id,
        );

        return response()->json(['data' => $this->contractData($contract->fresh(['template', 'signers.contact']))]);
    }

    public function send(Request $request, Contract $contract): JsonResponse
    {
        abort_unless($contract->state === ContractState::Draft, 422);

        $contract->markSent();

        if (! $contract->hasArtifact()) {
            RenderContractJob::dispatch($contract)->onQueue('render');
        }

        AuditLogger::record(
            event: 'contract.sent',
            actor: $this->actor($request),
            context: ['version' => $contract->version],
            subject: $contract->public_id,
            projectId: $contract->project_id,
        );

        return response()->json(['data' => $this->contractData($contract->fresh(['template', 'signers.contact']))]);
    }

    public function deadline(Request $request, Contract $contract): JsonResponse
    {
        $contract->update($request->validate([
            'sign_deadline_on' => ['nullable', 'date'],
        ]));

        return response()->json(['data' => $this->contractData($contract->fresh(['template', 'signers.contact']))]);
    }

    private function contractData(Contract $contract): ContractData
    {
        $roles = $contract->project->contacts->mapWithKeys(
            fn ($contact) => [$contact->id => ProjectContactRole::from($contact->pivot->role)]
        )->all();

        return ContractData::fromModel($contract, "/vertraege/{$contract->public_id}/pdf", $roles);
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
