<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Thread;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Portal\Support\PortalSettings;
use App\Quotes\Enums\QuoteStatus;
use App\Settings\Support\StudioSettingsResolver;
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
 * ProjectController asks for direct entry, so a booking the studio has not
 * made portal_visible is as absent here as it is 404 there.
 */
class HomeController extends Controller
{
    public function __invoke(StudioSettingsResolver $studio, PortalSettings $settings): Response|RedirectResponse
    {
        $contact = auth('portal')->user();

        $projects = $contact->visibleProjects()
            ->with([
                'primaryShoot',
                'latestQuote',
                'job.paymentPlan.installments',
                'galleries.currentVersion',
            ])
            ->get();

        // A single booking is no list to pick from — the overview would be
        // one card standing between the client and their own screen.
        if ($projects->count() === 1) {
            return redirect()->route('portal.projects.show', $projects->first()->public_id);
        }

        $bookings = $projects->map(fn (Project $project) => $this->booking($project))
            ->sortByDesc(fn (array $booking) => [$booking['year'] ?? 0, $booking['date'] ?? ''])
            ->values();

        return Inertia::render('Home', [
            'contact' => ['name' => $contact->name],
            // P-09 (#228): a sentence written for somebody who has not
            // booked anything reads as a mistake to a venue with forty. The
            // third room, the single booking, no longer lands here.
            'welcomeText' => $settings->welcomeText($contact, $bookings->isEmpty() ? 'inquiry' : 'many'),
            'pending' => $this->pending($contact, $projects, $settings),
            'bookings' => $bookings->all(),
            'phaseCounts' => $bookings->countBy('phase')->all(),
            'galleries' => $this->galleries($projects),
            'studio' => [
                'name' => $studio->displayName(),
                'messagingEnabled' => $settings->messagingEnabled($contact),
                // The footer's contact line had no source at all before P-09.
                ...$settings->contactLine(),
            ],
        ]);
    }

    /**
     * One booking as the client sees it. The phase is derived rather than
     * stored: delivered means there are pictures, upcoming means there is a
     * date still ahead, and everything else is still being settled. Three
     * words a client already understands, off facts that already exist.
     */
    private function booking(Project $project): array
    {
        $date = $project->primaryShoot?->date;
        $delivered = $project->galleries->contains(fn (Gallery $gallery) => $gallery->currentVersion !== null);

        return [
            'id' => $project->public_id,
            'name' => $project->name,
            'type' => $project->type->label(),
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
    private function cover(Project $project): ?string
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

    private function openAmount(Project $project): ?int
    {
        $installments = $project->job?->paymentPlan?->installments;

        if ($installments === null) {
            return null;
        }

        $open = $installments
            ->reject(fn (PaymentInstallment $installment) => $installment->status === PaymentInstallmentStatus::Paid)
            ->sum('amount');

        return $open > 0 ? (int) $open : null;
    }

    /**
     * "Das wartet auf euch", across every booking at once. Each row names the
     * booking it came from, because the whole point is that the client does
     * not have to open three projects to find out whether anything is open.
     *
     * Both money rows and the message row respect T-08's own switches: an
     * item the client cannot open is not something to put in front of them.
     */
    private function pending(Contact $contact, $projects, PortalSettings $settings): array
    {
        $rows = [];

        foreach ($projects as $project) {
            if ($settings->paymentStatusEnabled($contact)) {
                foreach ($project->job?->paymentPlan?->installments ?? [] as $installment) {
                    if ($installment->status === PaymentInstallmentStatus::Paid || $installment->due_on === null) {
                        continue;
                    }

                    $rows[] = [
                        'kind' => 'installment',
                        'amount' => (int) $installment->amount,
                        'dueOn' => $installment->due_on->format('Y-m-d'),
                        'overdue' => $installment->due_on->isPast(),
                        'booking' => $project->name,
                        'href' => "/projekte/{$project->public_id}/zahlungen",
                    ];
                }
            }

            $quote = $project->latestQuote;

            if ($quote?->status === QuoteStatus::Sent) {
                $rows[] = [
                    'kind' => 'quote',
                    'amount' => (int) $quote->total,
                    'dueOn' => $quote->valid_until?->format('Y-m-d'),
                    'overdue' => false,
                    'booking' => $project->name,
                    'href' => "/projekte/{$project->public_id}/angebot",
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
                    'booking' => $thread->project?->name,
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
            ->with(['project', 'latestMessage'])
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
            ->flatMap(fn (Project $project) => $project->galleries)
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
