<?php

namespace App\Portal\Actions;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Engagement\Actions\StartThread;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * P-03 (#222). The client corrects their own inquiry — the date they want,
 * the place, and what they wrote about it.
 *
 * Two things happen besides the write, and both are the point:
 *
 * An audit row, because a client changing a date is a fact the studio has to
 * be able to reconstruct later, and audit_logs is the append-only place this
 * app already keeps those.
 *
 * A line in the conversation, because a silent change to a wedding date is
 * how a photographer turns up on the wrong Saturday. The thread is where the
 * studio already reads what a client says; a change belongs there in words,
 * not only in a diff nobody opens.
 *
 * Refuses outright once the project is booked. The controller checks the
 * same thing to decide what to render — the check lives here too so a second
 * caller can never arrive ungated.
 */
class UpdateCaseDetails
{
    public function __construct(private readonly StartThread $start) {}

    public function __invoke(ClientCase $project, Contact $contact, array $details): void
    {
        if (! $project->clientMayEditDetails()) {
            throw new LogicException('This booking is settled — its date and place are contract content now.');
        }

        $shoot = $project->primaryShoot ?? $project->shoots()->orderBy('sort_order')->first();

        $before = [
            'date' => $shoot?->date?->format('Y-m-d'),
            'venue_name' => $shoot?->venue_name,
            'venue_city' => $shoot?->venue_city,
            'inquiry_text' => $project->lead?->inquiry_text,
        ];

        DB::transaction(function () use ($project, $shoot, $details) {
            $shoot?->update([
                'date' => $details['date'],
                'venue_name' => $details['venue_name'],
                'venue_city' => $details['venue_city'],
            ]);

            $project->lead?->update(['inquiry_text' => $details['inquiry_text']]);
        });

        $changed = array_keys(array_diff_assoc([
            'date' => $details['date'],
            'venue_name' => $details['venue_name'],
            'venue_city' => $details['venue_city'],
            'inquiry_text' => $details['inquiry_text'],
        ], $before));

        if ($changed === []) {
            return;
        }

        AuditLogger::record('portal.project.details_updated', null, [
            'contact' => $contact->public_id,
            'changed' => $changed,
        ], $project->public_id, $project->id);

        ($this->start)($project, $contact, __('portal.details_changed_subject'), $this->summary($shoot, $project));
    }

    private function summary(?Shoot $shoot, ClientCase $project): string
    {
        return __('portal.details_changed_body', [
            'date' => $shoot?->date?->format('d.m.Y') ?? __('portal.date_open'),
            'venue' => $shoot?->venue() ?? '—',
            'text' => $project->lead?->inquiry_text ?? '—',
        ]);
    }
}
