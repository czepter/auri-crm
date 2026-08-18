<?php

namespace App\Crm\Data;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One contact as a line in C-15's grouped table (#251).
 *
 * A contact is the sum of their projects, so the columns are reachability,
 * what they have with the studio, and what is open — not the postal address,
 * which contacts only carry for invoicing anyway.
 *
 * A couple is two contacts, never one row with a composed name. Each has
 * their own mail address and their own portal access, and a single row
 * holding "Meier & Kowalski" is the root of the portal's own "Hallo Meier"
 * finding: one name per record, no splitting of a joined string.
 */
#[TypeScript]
class ContactRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public int $projectCount,
        /** running · past · none — which group this row belongs in. */
        public string $standing,
        public ?string $nextDate,
        public ?string $latestProject,
    ) {}

    public static function fromContact(Contact $contact): self
    {
        // Ordered by the project's own primary shoot, so "next" is the day
        // this contact is next expected and not the newest row.
        $dated = $contact->projects
            ->map(fn (Project $project) => $project->primaryShoot?->date)
            ->filter()
            ->sort()
            ->values();

        $next = $dated->first(fn ($date) => $date->isFuture() || $date->isToday());

        return new self(
            id: $contact->public_id,
            name: $contact->name,
            email: $contact->email,
            phone: $contact->phone,
            projectCount: $contact->projects->count(),
            standing: match (true) {
                $next !== null => 'running',
                $contact->projects->isNotEmpty() => 'past',
                default => 'none',
            },
            nextDate: $next?->format('Y-m-d'),
            latestProject: $contact->projects->last()?->name,
        );
    }
}
