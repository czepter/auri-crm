<?php

namespace App\Crm\Actions;

use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Events\LeadCaptured;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Models\User;
use App\Portal\Support\PortalSettings;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Manual entry was P1's only capture path; I-03's "capture lead" endpoint is
 * the second, so every ClientCase, Lead, Contact and Shoot in the system is born
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

    public function __invoke(CaptureInquiryData $data, ?User $actor): ClientCase
    {
        return DB::transaction(function () use ($data, $actor) {
            $contact = $this->resolveContact($data);

            $project = ClientCase::create([
                'name' => $data->project_name,
                'type' => $data->type,
                'type_other' => $data->type_other,
                // Off for a stranger (vision §3.1), on for a client the
                // studio already shows its work to — that is this contact's
                // own portal_visible override, not a per-booking decision.
                'portal_visible' => $this->portal->projectsVisibleByDefault($contact),
            ]);

            $project->attachContact($contact, CaseContactRole::Primary);

            // Everyone else on the inquiry, each their own ProjectContact —
            // so a partner gets their own portal login rather than sharing
            // one identity, and can actually be reached.
            foreach ($data->people as $person) {
                if (! filled($person['name'] ?? null)) {
                    continue;
                }

                $project->attachContact(
                    $this->contactFor($person['name'], $person['email'] ?? null, $person['phone'] ?? null),
                    CaseContactRole::Partner,
                );
            }

            // A referrer who cannot be found is the same case as one nobody
            // recorded: the detail is optional by design, so a stale or
            // unknown public_id must not fail the whole capture.
            $referrer = $data->referred_by_contact_id === null
                ? null
                : Contact::query()->where('public_id', $data->referred_by_contact_id)->first();

            $capturedAt = $data->captured_at ?? now();

            $lead = Lead::create([
                'case_id' => $project->id,
                'source' => $data->source,
                'source_other' => $data->source_other,
                'stage' => $this->stages->anchored(StagePipeline::Lead, StageAnchor::Entry),
                'stage_entered_at' => $capturedAt,
                'inquiry_text' => $data->inquiry_text,
                'captured_via' => $data->captured_via,
                'captured_via_other' => $data->captured_via_other,
                'captured_by_id' => $actor?->id,
                'captured_at' => $capturedAt,
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
                'case_id' => $project->id,
                'kind' => $this->kindFor($data->type),
                'date' => $data->date,
                'call_time' => $data->call_time,
                'end_time' => $data->end_time,
                'venue_name' => $data->venue_name,
                'venue_city' => $data->venue_city,
                'status' => $this->stages->anchored(StagePipeline::Project, StageAnchor::Entry),
                'status_entered_at' => $capturedAt,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            AuditLogger::record(
                event: 'lead.captured',
                actor: $actor,
                context: ['source' => $data->source?->value, 'via' => $data->captured_via?->value],
                subject: $project->public_id,
                projectId: $lead->case_id,
                occurredAt: $data->captured_at === null ? null : CarbonImmutable::parse($data->captured_at),
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

        return $this->contactFor($data->contact_name, $data->contact_email, $data->contact_phone);
    }

    private function contactFor(?string $name, ?string $email, ?string $phone): Contact
    {
        $existing = filled($email)
            ? Contact::query()->where('email', $email)->first()
            : null;

        if ($existing !== null) {
            // A phone number the studio does not have yet is worth keeping;
            // one it already has is not worth overwriting from a form.
            if (filled($phone) && blank($existing->phone)) {
                $existing->update(['phone' => $phone]);
            }

            return $existing;
        }

        return Contact::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    private function kindFor(ProjectType|string|null $type): string
    {
        return Shoot::kindFor(is_string($type) ? $this->stages->type($type) : $type);
    }
}
