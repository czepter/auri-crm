<?php

namespace App\Crm\Actions;

use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Events\LeadCaptured;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Models\User;
use App\Portal\Support\PortalSettings;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Manual entry was P1's only capture path; I-03's "capture lead" endpoint is
 * the second, so every Project, Lead, Contact and Shoot in the system is born
 * here either way.
 *
 * One transaction: a half-created inquiry — a project with no lead, a lead with
 * no shoot — is worse than a rejected one, because nothing downstream expects
 * the gap and nobody would go looking for it.
 *
 * $actor is nullable for the api surface: an ApiToken (I-01) is not a User,
 * so a lead captured through the api carries no captured_by_id — see the
 * migration that dropped that column's NOT NULL for the same reason.
 */
class CaptureInquiry
{
    public function __construct(
        private readonly PortalSettings $portal,
        private readonly CrmVocabulary $stages,
        private readonly WriteProjectFieldValues $writeFields,
    ) {}

    public function __invoke(CaptureInquiryData $data, ?User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            $contact = $this->resolveContact($data);

            $project = Project::create([
                'name' => $data->project_name,
                'type' => $data->type,
                'type_other' => $data->type_other,
                // Off for a stranger (vision §3.1), on for a client the
                // studio already shows its work to — that is this contact's
                // own portal_visible override, not a per-booking decision.
                'portal_visible' => $this->portal->projectsVisibleByDefault($contact),
            ]);

            $project->attachContact($contact, ProjectContactRole::Primary);

            // Everyone else on the inquiry, each their own ProjectContact —
            // so a partner gets their own portal login rather than sharing
            // one identity, and can actually be reached.
            foreach ($data->people as $person) {
                if (! filled($person['name'] ?? null)) {
                    continue;
                }

                $project->attachContact(
                    Contact::create([
                        'name' => $person['name'],
                        'email' => $person['email'] ?? null,
                        'phone' => $person['phone'] ?? null,
                    ]),
                    ProjectContactRole::Partner,
                );
            }

            // A referrer who cannot be found is the same case as one nobody
            // recorded: the detail is optional by design, so a stale or
            // unknown public_id must not fail the whole capture.
            $referrer = $data->referred_by_contact_id === null
                ? null
                : Contact::query()->where('public_id', $data->referred_by_contact_id)->first();

            $lead = Lead::create([
                'project_id' => $project->id,
                'source' => $data->source,
                'source_other' => $data->source_other,
                'stage' => $this->stages->anchored(StagePipeline::Lead, StageAnchor::Entry),
                'stage_entered_at' => now(),
                'inquiry_text' => $data->inquiry_text,
                'captured_via' => $data->captured_via,
                'captured_via_other' => $data->captured_via_other,
                'captured_by_id' => $actor?->id,
                'captured_at' => $data->captured_at ?? now(),
                'referred_by_contact_id' => $referrer?->id,
            ]);

            // C-25: was dieser Typ über den Namen hinaus verlangt. Durch den
            // einen Schreiber, damit die Erfassung denselben Definitionen
            // gehorcht wie Projektbildschirm und API — „required" bleibt hier
            // ungeprüft, was die Entscheidung jener Action für genau diesen
            // Moment ist.
            if ($data->fields !== []) {
                ($this->writeFields)($project, $data->fields, $actor);
            }

            // Exactly one shoot, and it is the primary one. Mockup 03 collects
            // a single main date; further shoots are added on the project
            // screen, which is what makes is_primary unambiguous here.
            Shoot::create([
                'project_id' => $project->id,
                'kind' => $this->kindFor($data->type),
                'date' => $data->date,
                'status' => $this->stages->anchored(StagePipeline::Project, StageAnchor::Entry),
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            AuditLogger::record(
                event: 'lead.captured',
                actor: $actor,
                context: ['source' => $data->source->value, 'via' => $data->captured_via->value],
                subject: $lead->public_id,
                projectId: $lead->project_id,
            );

            LeadCaptured::dispatch($lead);

            return $project;
        });
    }

    /**
     * An address already on file is the same person enquiring again, not a
     * second contact — and C-01's partial unique index on (tenant_id, email)
     * would refuse the insert anyway, with a query exception rather than
     * anything a caller could show a human.
     *
     * The stored name wins over the submitted one. A client who types
     * "L. Meier" into a public form is not renaming the studio's record of
     * Lena Meier, and C-08's form is the first caller that can reach this
     * without a typeahead having already resolved the contact by id.
     */
    private function resolveContact(CaptureInquiryData $data): Contact
    {
        if (filled($data->contact_id)) {
            return Contact::query()->where('public_id', $data->contact_id)->firstOrFail();
        }

        $existing = filled($data->contact_email)
            ? Contact::query()->where('email', $data->contact_email)->first()
            : null;

        if ($existing !== null) {
            // A phone number the studio does not have yet is worth keeping;
            // one it already has is not worth overwriting from a form.
            if (filled($data->contact_phone) && blank($existing->phone)) {
                $existing->update(['phone' => $data->contact_phone]);
            }

            return $existing;
        }

        return Contact::create([
            'name' => $data->contact_name,
            'email' => $data->contact_email,
            'phone' => $data->contact_phone,
        ]);
    }

    /**
     * The opening shoot of a project takes the project's own shape, where the
     * two share a name. A studio's own type — "Elopement", "Neugeboren" — has
     * no ShootKind of its own and opens as sonstige; the shoot's kind is
     * editable on the project screen, and guessing it from the type's name
     * would be wrong more often than blank is.
     */
    private function kindFor(ProjectType|string|null $type): ShootKind
    {
        $key = $type instanceof ProjectType ? $type->key : $type;

        return ShootKind::tryFrom((string) $key) ?? ShootKind::Other;
    }
}
