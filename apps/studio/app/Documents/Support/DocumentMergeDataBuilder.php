<?php

namespace App\Documents\Support;

use App\Crm\Models\Project;
use App\Settings\Support\StudioSettingsResolver;

/**
 * The one place a merge-field array is assembled from real data — T-12's
 * "resolved from one place" acceptance criterion. App\Contracts\Jobs\
 * RenderContractJob calls this rather than building its own array, and
 * whatever eventually renders a quote or a transactional mail calls it too:
 * one vocabulary, one builder, so `{{ project.name }}` can never mean two
 * different things depending which document reads it.
 *
 * Called at render time, never live from a controller — see RenderContractJob's
 * own doc comment for why: a document that read project data live would
 * silently change after a client already saw it.
 */
class DocumentMergeDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function fromProject(Project $project, StudioSettingsResolver $settings): array
    {
        $contact = $project->primaryContact();
        $studio = $settings->attributes();

        return [
            'project' => [
                'name' => $project->name,
                'type' => $project->type->label(),
            ],
            'contact' => [
                'name' => $contact?->name ?? '',
                'email' => $contact?->email ?? '',
            ],
            'job' => [
                'bookedAt' => $project->job?->booked_at?->format('d.m.Y') ?? '',
            ],
            'studio' => [
                'name' => $studio['legal_name'] ?: ($studio['display_name'] ?? ''),
                'legalForm' => $studio['legal_form'] ?? '',
                'street' => $studio['street'] ?? '',
                'postcode' => $studio['postcode'] ?? '',
                'city' => $studio['city'] ?? '',
                'country' => $studio['country'] ?? '',
                'taxNumber' => $studio['tax_number'] ?? '',
                'vatId' => $studio['vat_id'] ?? '',
                'registerNumber' => $studio['register_number'] ?? '',
                'registerCourt' => $studio['register_court'] ?? '',
                'representative' => $studio['representative'] ?? '',
                'website' => $studio['website'] ?? '',
                // The stored file path, not a URL — no template embeds it as
                // an <img> today; see docs/documents.md.
                'logoPath' => $settings->logoPath() ?? '',
            ],
            'settings' => [
                'widerrufText' => $settings->widerrufIncluded() ? ($settings->widerrufText() ?? '') : '',
            ],
        ];
    }
}
