<?php

namespace App\Migration\Actions;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Migration\Support\ImportPlan;
use Illuminate\Support\Facades\DB;

/**
 * Writes exactly the plan PlanHistoricalImport built — nothing here decides
 * what to create, only how. One transaction: a partial import (contacts
 * written, the project that names them failing) is worse than no import at
 * all, because a second run then has no way to tell already-imported rows
 * apart from ones that never landed.
 *
 * Never called on a plan with errors left unresolved — ImportHistoricalDataCommand
 * only calls this after printing them, same as it always has; skipping the
 * bad rows and writing the rest silently would mean the report the studio
 * read before running --commit no longer matches what got written.
 */
class CommitHistoricalImport
{
    public function __invoke(ImportPlan $plan): array
    {
        return DB::transaction(function () use ($plan) {
            $contactsByRef = $this->createContacts($plan);
            $projectsByRef = $this->createProjects($plan, $contactsByRef);

            foreach ($plan->jobs['toCreate'] as $row) {
                Job::create([
                    'project_id' => $projectsByRef[$row['project_ref']]->id,
                    'booked_at' => $row['booked_at'],
                    // No staff member behind this trigger — see BookedVia's
                    // own doc comment, which names X-01 by this exact case.
                    'booked_by_id' => null,
                    'booked_via' => $row['booked_via'],
                ]);
            }

            return [
                'contacts' => count($plan->contacts['toCreate']),
                'contacts_reused' => count($plan->contacts['toReuse']),
                'projects' => count($plan->projects['toCreate']),
                'jobs' => count($plan->jobs['toCreate']),
            ];
        });
    }

    /** @return array<string, Contact> ref => the row now backing it, new or reused */
    private function createContacts(ImportPlan $plan): array
    {
        $byRef = [];

        foreach ($plan->contacts['toCreate'] as $row) {
            $byRef[$row['ref']] = Contact::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
            ]);
        }

        foreach ($plan->contacts['toReuse'] as $row) {
            $byRef[$row['ref']] = $row['contact'];
        }

        return $byRef;
    }

    /**
     * @param  array<string, Contact>  $contactsByRef
     * @return array<string, Project> ref => the project it created
     */
    private function createProjects(ImportPlan $plan, array $contactsByRef): array
    {
        $byRef = [];

        foreach ($plan->projects['toCreate'] as $row) {
            $project = Project::create([
                'name' => $row['name'],
                'type' => $row['type'],
            ]);

            $project->attachContact($contactsByRef[$row['primary_contact_ref']], ProjectContactRole::Primary);

            $byRef[$row['ref']] = $project;
        }

        return $byRef;
    }
}
