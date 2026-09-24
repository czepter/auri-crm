<?php

namespace App\Gallery\Actions;

use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Enums\LeadSource;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Mockup 01's *Nach der Lieferung* column: the occasions the mockup files as
 * "kept deliberately personal".
 *
 * Neither row sends anything, and neither is a thread. E-06 makes the client
 * the only party who can originate a conversation — stated in StartThread's
 * own docblock, in PostMessage's, and beside the route — so a thank-you here
 * is a phone call, which is the truer action anyway. A thank-you the machine
 * sends is not a thank-you.
 *
 * The mockup's "— Album nie angeboten" clause is absent: no album concept
 * exists to have been offered, and inventing one to fill a clause is the
 * failure this screen's design explicitly warns against. The row states the
 * expiry; the button opens the gallery.
 */
class ReadAfterDeliveryColumn
{
    private const EXPIRY_WINDOW_DAYS = 30;

    /**
     * @return Collection<int, DomainRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        $visible = $viewer === null ? null : ClientCase::query()->visibleTo($viewer)->select('cases.id');

        return $this->referrals($visible)->concat($this->expiringGalleries($visible))->values();
    }

    /**
     * A referral whose project actually became work. Gated on the project
     * being booked via whereHas — an EXISTS subquery, not a join: before the
     * booking the project is a pursuit, and thanking someone for a pursuit is
     * premature.
     *
     * @return Collection<int, DomainRowData>
     */
    private function referrals(?Builder $visible): Collection
    {
        return Lead::query()
            ->where('source', LeadSource::Referral)
            ->whereNotNull('referred_by_contact_id')
            ->when($visible !== null, fn ($query) => $query->whereIn('case_id', $visible))
            ->whereHas('case', fn ($project) => $project->booked())
            ->with(['case', 'referredBy'])
            ->get()
            ->map(fn (Lead $lead) => new DomainRowData(
                projectId: $lead->case->public_id,
                who: $lead->referredBy->name,
                what: 'hat '.$lead->case->name.' empfohlen',
                emphasis: null,
                whatTail: null,
                action: RowActionData::tel('Bedanken', $lead->referredBy->phone),
            ));
    }

    /**
     * @return Collection<int, DomainRowData>
     */
    private function expiringGalleries(?Builder $visible): Collection
    {
        return Gallery::query()
            // Eine Galerie ohne Buchung hat in einer Spalte ueber Buchungen
            // nichts verloren.
            ->whereNotNull('case_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(self::EXPIRY_WINDOW_DAYS))
            ->when($visible !== null, fn ($query) => $query->whereIn('case_id', $visible))
            ->with('case')
            ->orderBy('expires_at')
            ->get()
            ->map(fn (Gallery $gallery) => new DomainRowData(
                projectId: $gallery->case->public_id,
                who: $gallery->case->name,
                what: 'Galerie läuft',
                emphasis: 'am '.$gallery->expires_at->format('d.m.Y').' ab',
                whatTail: null,
                action: RowActionData::link('Album anbieten', "/galerien/{$gallery->public_id}"),
            ));
    }
}
