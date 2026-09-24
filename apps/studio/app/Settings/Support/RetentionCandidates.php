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
            ->whereHas('cases')
            ->whereDoesntHave('cases', fn ($project) => $project->where(fn ($p) => $p
                ->whereNotIn('close_outcome', [LeadOutcome::Lost, LeadOutcome::Declined])
                ->orWhereNull('close_outcome')
                ->when($cutoff, fn ($p) => $p->orWhere('closed_at', '>=', $cutoff))
                ->orWhereDoesntHave('lead', fn ($lead) => $lead
                    ->when($cutoff, fn ($lead) => $lead->where('updated_at', '<', $cutoff)))
                ->orWhereNotNull('booked_at')));
    }

    /** @return Collection<int, int> */
    public static function invoicedContactIds(): Collection
    {
        return Invoice::query()->whereNotNull('contact_id')->pluck('contact_id')
            ->merge(DB::table('case_contacts')
                ->whereIn('case_id', Invoice::query()->whereNotNull('case_id')->select('case_id'))
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
        $contacts = self::query()->with('cases.lead')->get();

        $projectIds = $contacts->flatMap->cases->pluck('id');
        $messages = Thread::query()->whereIn('case_id', $projectIds)->withCount('messages')->get()
            ->groupBy('case_id')->map->sum('messages_count');
        $notes = InternalNote::query()->whereIn('case_id', $projectIds)->selectRaw('case_id, count(*) as n')->groupBy('case_id')->pluck('n', 'case_id');
        $quotes = Quote::query()->whereIn('case_id', $projectIds)->selectRaw('case_id, count(*) as n')->groupBy('case_id')->pluck('n', 'case_id');
        $galleries = Gallery::query()->whereIn('case_id', $projectIds)->selectRaw('case_id, count(*) as n')->groupBy('case_id')->pluck('n', 'case_id');

        return $contacts->map(function (Contact $contact) use ($invoiced, $messages, $notes, $quotes, $galleries) {
            $ids = $contact->cases->pluck('id');
            $leads = $contact->cases->pluck('lead')->filter();
            $latest = $leads->sortByDesc('updated_at')->first();
            $case = $contact->cases->firstWhere('id', $latest->case_id);
            $sum = fn (Collection $by) => (int) $ids->sum(fn ($id) => $by[$id] ?? 0);

            return new RetentionCandidateData(
                id: $contact->public_id,
                name: $contact->name,
                type: $contact->cases->first()->typeLabel(),
                text: $latest->inquiry_text === null ? null : str($latest->inquiry_text)->limit(120)->toString(),
                outcome: $case->close_outcome->value,
                outcomeLabel: $case->close_outcome->label(),
                // Closing wrote the lead before #521 and so reset its updated_at; the close itself stands in for that now.
                idleMonths: (int) max($latest->updated_at, $case->closed_at)->diffInMonths(now()),
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
