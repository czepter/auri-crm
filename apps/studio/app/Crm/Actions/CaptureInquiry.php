<?php

namespace App\Crm\Actions;

use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Events\LeadCaptured;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
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
    public function __invoke(CaptureInquiryData $data, ?User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            $contact = $this->resolveContact($data);

            $project = Project::create([
                'name' => $data->project_name,
                'type' => $data->type,
                // Never true at capture: a raw inquiry must not surface in a
                // client portal the moment it arrives (vision §3.1).
                'portal_visible' => false,
            ]);

            $project->attachContact($contact, ProjectContactRole::Primary);

            // One field in the UI, a second ProjectContact underneath — so in
            // P2 the partner gets their own portal login rather than sharing
            // one identity with the primary contact.
            if (filled($data->partner_name)) {
                $project->attachContact(
                    Contact::create(['name' => $data->partner_name]),
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
                'stage' => LeadStage::New,
                'stage_entered_at' => now(),
                'inquiry_text' => $data->inquiry_text,
                'captured_via' => $data->captured_via,
                'captured_by_id' => $actor?->id,
                'referred_by_contact_id' => $referrer?->id,
            ]);

            // Exactly one shoot, and it is the primary one. Mockup 03 collects
            // a single main date; further shoots are added on the project
            // screen, which is what makes is_primary unambiguous here.
            Shoot::create([
                'project_id' => $project->id,
                'kind' => $this->kindFor($data->type),
                'date' => $data->date,
                'status' => ShootStatus::Requested,
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

    private function resolveContact(CaptureInquiryData $data): Contact
    {
        if (filled($data->contact_id)) {
            return Contact::query()->where('public_id', $data->contact_id)->firstOrFail();
        }

        return Contact::create([
            'name' => $data->contact_name,
            'email' => $data->contact_email,
            'phone' => $data->contact_phone,
        ]);
    }

    /** The opening shoot of a project takes the project's own shape. */
    private function kindFor(ProjectType $type): ShootKind
    {
        return match ($type) {
            ProjectType::Wedding => ShootKind::Wedding,
            ProjectType::Portrait => ShootKind::Portrait,
            ProjectType::Other => ShootKind::Other,
        };
    }
}
