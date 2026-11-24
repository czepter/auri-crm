<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Models\Thread;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Payments\Data\PaymentPlanData;
use App\Portal\Actions\ReadClientNextStep;
use App\Portal\Support\PortalOffers;
use App\Portal\Support\PortalSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * P-01/P-02 (#220, #221). The place a client lands, so what belongs on it is
 * what concerns them — not a project switcher.
 *
 * Four sections, in this order: what is waiting on them across every booking,
 * the bookings themselves, their galleries, and a person to write to. A
 * section with nothing in it is absent rather than empty: a dashboard of
 * blank boxes tells a client their photographer has nothing for them.
 *
 * Bookings are equal-ranked and split by year. The year is a finding aid, not
 * a hierarchy — a venue with forty corporate dates and a couple with one
 * wedding read the same way, and P-02's filter and year rollup are the only
 * things that change at that scale, both driven by counts computed here.
 *
 * Everything is scoped through Contact::visibleProjects(), the same question
 * CaseController asks for direct entry, so a booking the studio has not
 * made portal_visible is as absent here as it is 404 there.
 */
class HomeController extends Controller
{
    public function __invoke(PortalSettings $settings, PortalOffers $offers, ReadClientNextStep $next): Response|RedirectResponse
    {
        $contact = auth('portal')->user();

        $projects = $contact->visibleProjects()
            ->with([
                'primaryShoot',
                'latestQuote',
                'latestContract.signature',
                'paymentPlan.installments',
                'galleries.currentVersion',
            ])
            ->get();

        // A single booking is no list to pick from — the overview would be
        // one card standing between the client and their own screen.
        if ($projects->count() === 1) {
            return redirect()->route('portal.projects.show', $projects->first()->public_id);
        }

        $bookings = $projects->map(fn (ClientCase $project) => $this->booking($project))
            ->sortBy(fn (array $booking) => [$booking['year'] ?? PHP_INT_MAX, $booking['date'] ?? ''])
            ->values();

        return Inertia::render('Home', [
            'contact' => ['name' => $contact->name],
            'welcomeText' => $settings->welcomeText($contact, $this->welcomeState($bookings->all())),
            'welcomeState' => $this->welcomeState($bookings->all()),
            'pending' => $this->pending($contact, $projects, $settings, $next),
            'bookings' => $bookings->all(),
            'phaseCounts' => $bookings->countBy('phase')->all(),
            'galleries' => $this->galleries($projects),
            // Steht im Entwurf unter den Galerien, nicht über dem Offenen:
            // ein Angebot des Studios ist keine offene Handlung des Kunden.
            'offers' => $offers->for($contact),
            'studio' => [
                'messagingEnabled' => $settings->messagingEnabled($contact),
                ...$settings->contactLine(),
            ],
        ]);
    }

    /**
     * Which of the three greetings this client is owed. Nothing booked is
     * the stranger with an inquiry; everything delivered is the client whose
     * pictures are done — the two used to share one text.
     *
     * @param  list<array{phase: string}>  $bookings
     */
    private function welcomeState(array $bookings): string
    {
        if ($bookings === []) {
            return 'inquiry';
        }

        return collect($bookings)->every(fn (array $booking) => $booking['phase'] === 'delivered')
            ? 'done'
            : 'active';
    }

    /**
     * One booking as the client sees it. The phase is derived rather than
     * stored: delivered means there are pictures, upcoming means there is a
     * date still ahead, and everything else is still being settled. Three
     * words a client already understands, off facts that already exist.
     */
    private function booking(ClientCase $project): array
    {
        $date = $project->primaryShoot?->date;
        $delivered = $project->galleries->contains(fn (Gallery $gallery) => $gallery->currentVersion !== null);

        return [
            'id' => $project->public_id,
            'name' => $project->name,
            'type' => $project->type->name,
            'date' => $date?->format('Y-m-d'),
            'year' => $date?->year,
            'venue' => $project->primaryShoot?->venue(),
            'phase' => match (true) {
                $delivered => 'delivered',
                $date !== null && $date->isFuture() => 'upcoming',
                default => 'open',
            },
            'openAmount' => $this->openAmount($project),
            // A booking with a delivered picture wears it. One without gets
            // its year set in the display face instead of an empty grey
            // rectangle, which on paper reads as an image that failed.
            'cover' => $this->cover($project),
        ];
    }

    /** The first asset of this booking's first published gallery, or nothing. */
    private function cover(ClientCase $project): ?string
    {
        foreach ($project->galleries as $gallery) {
            $version = $gallery->currentVersion;
            $first = $version?->assets[0]['key'] ?? null;

            if ($first !== null) {
                return "/galerie-bilder/{$version->public_id}/{$first}";
            }
        }

        return null;
    }

    private function openAmount(ClientCase $project): ?int
    {
        $open = PaymentPlanData::forCase($project)?->openTotal ?? 0;

        return $open > 0 ? $open : null;
    }

    /**
     * "Das wartet auf euch", across every booking at once. Each row names the
     * booking it came from, because the whole point is that the client does
     * not have to open three projects to find out whether anything is open.
     *
     * One step per booking, from the same action the project page reads, so
     * nothing is listed that the client cannot do. The message row respects
     * T-08's own switch.
     */
    private function pending(Contact $contact, $projects, PortalSettings $settings, ReadClientNextStep $next): array
    {
        $rows = [];

        foreach ($projects as $project) {
            $step = ($next($project, $contact))[0] ?? null;

            if ($step !== null) {
                $rows[] = [
                    'kind' => $step['kind'],
                    'amount' => $step['amount'],
                    'dueOn' => $step['dueOn'],
                    'overdue' => $step['overdue'],
                    'booking' => $project->name,
                    'href' => $step['href'],
                ];
            }
        }

        if ($settings->messagingEnabled($contact)) {
            foreach ($this->unanswered($contact) as $thread) {
                $rows[] = [
                    'kind' => 'message',
                    'amount' => null,
                    'dueOn' => null,
                    'overdue' => false,
                    'booking' => $thread->case?->name,
                    'subject' => $thread->subject,
                    'href' => "/nachrichten?unterhaltung={$thread->public_id}",
                ];
            }
        }

        return $rows;
    }

    /**
     * Threads where the studio spoke last. Whoever wrote the newest line owes
     * or is owed the next one — the same rule Thread::latestMessage() was
     * built for, asked from the client's side here.
     */
    private function unanswered(Contact $contact)
    {
        return $contact->threads()
            ->with(['case', 'latestMessage'])
            ->get()
            ->filter(fn (Thread $thread) => $thread->latestMessage?->contact_id === null
                && $thread->latestMessage !== null);
    }

    /**
     * Direct entry to the pictures, without the detour through the booking —
     * the link that did not exist at all before P-01. Expired galleries are
     * left out: the link behind them no longer delivers anything.
     *
     * The cover is the published version's first asset, served by this
     * surface's own gated image route; a signed-in client on the booking
     * passes that gate without the guest password (GalleryAccessSession).
     */
    private function galleries($projects): array
    {
        return $projects
            ->flatMap(fn (ClientCase $project) => $project->galleries)
            ->filter(fn (Gallery $gallery) => $gallery->currentVersion !== null
                && ($gallery->expires_at === null || $gallery->expires_at->isFuture()))
            ->map(function (Gallery $gallery) {
                $version = $gallery->currentVersion;
                $first = $version->assets[0]['key'] ?? null;

                return [
                    'id' => $gallery->public_id,
                    'title' => $gallery->title,
                    'count' => count($version->assets),
                    'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
                    'cover' => $first === null
                        ? null
                        : "/galerie-bilder/{$version->public_id}/{$first}",
                ];
            })
            ->values()
            ->all();
    }
}
