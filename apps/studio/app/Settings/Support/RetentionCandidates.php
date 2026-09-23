<?php

namespace App\Settings\Support;

use App\Crm\Enums\LeadOutcome;
use App\Crm\Models\Contact;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Engagement\Models\Thread;
use App\Gallery\Models\Gallery;
use App\Payments\Models\Invoice;
use App\Quotes\Models\Quote;
use App\Reporting\Support\BookingFunnel;
use App\Settings\Data\RetentionCandidateData;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kontakte, deren sämtliche Projekte unbuchte, verlorene oder abgelehnte
 * Anfragen sind. Rechnungen (§ 147 AO) bleiben: wer eine hat, direkt oder
 * über ein Projekt, fällt heraus. Ein Kontakt, den der Lauf schon bearbeitet
 * hat, kommt nicht wieder; eine strengere Wahl später greift nur für neue.
 */
class RetentionCandidates
{
    /** @return Builder<Contact> */
    public static function query(?CarbonInterface $cutoff = null): Builder
    {
        return Contact::query()
            ->whereNull('dsgvo_erased_at')
            ->whereNull('retention_applied_at')
            ->whereHas('projects')
            ->whereDoesntHave('projects', fn ($project) => $project->where(fn ($p) => $p
                ->whereDoesntHave('lead', fn ($lead) => $lead
                    ->whereIn('outcome', [LeadOutcome::Lost, LeadOutcome::Declined])
                    ->when($cutoff, fn ($lead) => $lead->where('updated_at', '<', $cutoff)))
                ->orWhereNotNull('booked_at')));
    }

    /** @return Collection<int, int> */
    public static function invoicedContactIds(): Collection
    {
        return Invoice::query()->whereNotNull('contact_id')->pluck('contact_id')
            ->merge(DB::table('project_contacts')
                ->whereIn('project_id', Invoice::query()->whereNotNull('project_id')->select('project_id'))
                ->pluck('contact_id'))
            ->unique()
            ->values();
    }

    /**
     * Alle Kandidaten mit Alter, ohne Frist: die Seite rechnet Frist und Wahl
     * im Browser durch, ohne bei jedem Klick zurückzufragen.
     *
     * @return list<RetentionCandidateData>
     */
    public static function preview(): array
    {
        $invoiced = self::invoicedContactIds()->flip();
        $contacts = self::query()->with('projects.lead')->get();

        $projectIds = $contacts->flatMap->projects->pluck('id');
        $messages = Thread::query()->whereIn('project_id', $projectIds)->withCount('messages')->get()
            ->groupBy('project_id')->map->sum('messages_count');
        $notes = InternalNote::query()->whereIn('project_id', $projectIds)->selectRaw('project_id, count(*) as n')->groupBy('project_id')->pluck('n', 'project_id');
        $quotes = Quote::query()->whereIn('project_id', $projectIds)->selectRaw('project_id, count(*) as n')->groupBy('project_id')->pluck('n', 'project_id');
        $galleries = Gallery::query()->whereIn('project_id', $projectIds)->selectRaw('project_id, count(*) as n')->groupBy('project_id')->pluck('n', 'project_id');

        return $contacts->map(function (Contact $contact) use ($invoiced, $messages, $notes, $quotes, $galleries) {
            $ids = $contact->projects->pluck('id');
            $leads = $contact->projects->pluck('lead')->filter();
            $latest = $leads->sortByDesc('updated_at')->first();
            $sum = fn (Collection $by) => (int) $ids->sum(fn ($id) => $by[$id] ?? 0);

            return new RetentionCandidateData(
                id: $contact->public_id,
                name: $contact->name,
                type: $contact->projects->first()->typeLabel(),
                text: $latest->inquiry_text === null ? null : str($latest->inquiry_text)->limit(120)->toString(),
                outcome: $latest->outcome->value,
                outcomeLabel: $latest->outcome->label(),
                idleMonths: (int) $latest->updated_at->diffInMonths(now()),
                invoiced: $invoiced->has($contact->id),
                leads: $leads->count(),
                messages: $sum($messages) + $sum($notes),
                offers: $sum($quotes),
                galleries: $sum($galleries),
            );
        })->sortByDesc('idleMonths')->values()->all();
    }

    /** @return array{total: int, booked: int} */
    public static function funnel(): array
    {
        return [
            'total' => Lead::query()->count(),
            'booked' => Lead::query()->where('stage', BookingFunnel::wonKey())->count(),
        ];
    }
}
