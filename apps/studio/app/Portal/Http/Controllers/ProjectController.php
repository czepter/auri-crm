<?php

namespace App\Portal\Http\Controllers;

use App\Contracts\Models\Contract;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Portal\Actions\UpdateProjectDetails;
use App\Quotes\Enums\QuoteStatus;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One booking, in one of two shapes.
 *
 * P-03 (#222) before anything is reserved: two names, a wished-for date and
 * a kind. No pictures, no appointments, no quote, no contract, no payments —
 * so it is a letter, not a dashboard of empty boxes, and the details the
 * client typed into the inquiry are theirs to correct.
 *
 * P-07 (#226) once it is booked: the picture first, then a sentence saying
 * where things stand, then the pictures, the appointments, and the paperwork
 * last. The old screen had exactly the reverse order and read like a file.
 * The same details are here, read-only, because from `gebucht` the date and
 * the place are contract content — in their place stands the way to ask for
 * a change.
 *
 * Both ask Contact::visibleProjects() the same question the home does, so a
 * project the contact is not on and one the studio has not made
 * portal_visible fail identically — a 404 through firstOrFail(), never a
 * 403: the difference between "not yours" and "does not exist" is not this
 * surface's to give away.
 */
class ProjectController extends Controller
{
    public function __invoke(string $project, StudioSettingsResolver $settings): Response
    {
        $contact = auth('portal')->user();

        $found = $this->find($project);
        $editable = $found->clientMayEditDetails();

        $shared = [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'studio' => ['name' => $settings->displayName(), 'messagingEnabled' => $settings->messagingEnabled()],
        ];

        return $editable
            ? Inertia::render('Projects/Inquiry', [...$shared, 'project' => $this->inquiry($found)])
            : Inertia::render('Projects/Show', [...$shared, 'project' => $this->booking($found, $settings)]);
    }

    /**
     * P-03's write path — the one the portal had none of. Gated twice on
     * purpose: here, so a booked project answers 404 rather than 422, and
     * again inside UpdateProjectDetails, so no second caller can arrive
     * without the check.
     */
    public function update(Request $request, string $project, UpdateProjectDetails $update): RedirectResponse
    {
        $found = $this->find($project);

        abort_unless($found->clientMayEditDetails(), 404);

        $update($found, auth('portal')->user(), $request->validate([
            'date' => ['nullable', 'date'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['nullable', 'string', 'max:255'],
            'inquiry_text' => ['nullable', 'string', 'max:5000'],
        ]));

        return back();
    }

    private function find(string $project): Project
    {
        return auth('portal')->user()->visibleProjects()
            ->where('projects.public_id', $project)
            ->firstOrFail();
    }

    /** What the client told us, as they can still change it. */
    private function inquiry(Project $found): array
    {
        $shoot = $found->primaryShoot ?? $found->shoots()->orderBy('sort_order')->first();

        return [
            'id' => $found->public_id,
            'name' => $found->name,
            'type' => $found->type->label(),
            'date' => $shoot?->date?->format('Y-m-d'),
            'venueName' => $shoot?->venue_name,
            'venueCity' => $shoot?->venue_city,
            'inquiryText' => $found->lead?->inquiry_text,
            'contact' => [
                'email' => auth('portal')->user()->email,
                'phone' => auth('portal')->user()->phone,
            ],
        ];
    }

    /** The booked booking: cover, one sentence, pictures, dates, paperwork. */
    private function booking(Project $found, StudioSettingsResolver $settings): array
    {
        $found->load([
            'shoots' => fn ($query) => $query->uncancelled()->orderByRaw('date is null, date'),
            'latestContract.signature', 'latestQuote', 'job.paymentPlan.installments',
            'galleries.currentVersion', 'primaryShoot',
        ]);

        $gallery = $found->galleries->first(fn (Gallery $one) => $one->currentVersion !== null);
        $version = $gallery?->currentVersion;

        return [
            'id' => $found->public_id,
            'name' => $found->name,
            'type' => $found->type->label(),
            'date' => $found->primaryShoot?->date?->format('Y-m-d'),
            'venue' => $found->primaryShoot?->venue(),
            'shoots' => $found->shoots->map(fn (Shoot $shoot) => [
                'id' => $shoot->public_id,
                'title' => $shoot->label ?? $shoot->kind->label(),
                'date' => $shoot->date?->format('Y-m-d'),
                'callTime' => $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
                'venue' => $shoot->venue(),
                // The client is told where their shoot stands, in the same
                // words the studio uses. The temperature differs, not the
                // fact: nothing here is coloured as a warning, because the
                // studio chases and the client is not chased in their own
                // portal.
                'status' => $shoot->status->label(),
            ])->all(),
            // The entry point that was missing entirely: a strip of the
            // delivered pictures and a way into all of them.
            'gallery' => $gallery === null ? null : [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'count' => count($version->assets),
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
                'strip' => collect($version->assets)->take(4)
                    ->map(fn (array $asset) => "/galerie-bilder/{$version->public_id}/{$asset['key']}")
                    ->values()->all(),
            ],
            'documents' => $this->documents($found, $settings),
            'details' => [
                'date' => $found->primaryShoot?->date?->format('Y-m-d'),
                'callTime' => $found->primaryShoot?->call_time === null
                    ? null
                    : substr((string) $found->primaryShoot->call_time, 0, 5),
                'venue' => $found->primaryShoot?->venue(),
                'email' => auth('portal')->user()->email,
                'phone' => auth('portal')->user()->phone,
            ],
        ];
    }

    /**
     * The paperwork, with its state in the row rather than behind a click —
     * three grey text links before this. Each entry respects T-08's own
     * switch, so nothing here leads to a route that answers 404.
     */
    private function documents(Project $found, StudioSettingsResolver $settings): array
    {
        $rows = [];
        $plan = $found->job?->paymentPlan;

        if ($plan !== null && $settings->paymentStatusEnabled()) {
            $next = $plan->installments
                ->reject(fn (PaymentInstallment $one) => $one->status === PaymentInstallmentStatus::Paid)
                ->sortBy('due_on')
                ->first();

            $rows[] = [
                'kind' => 'payments',
                'href' => "/projekte/{$found->public_id}/zahlungen",
                'dueOn' => $next?->due_on?->format('Y-m-d'),
                'amount' => $next === null ? null : (int) $next->amount,
                'settled' => $next === null,
            ];
        }

        if ($found->latestContract !== null) {
            $rows[] = [
                'kind' => 'contract',
                'href' => "/projekte/{$found->public_id}/vertrag",
                'signedOn' => $this->signedOn($found->latestContract),
                'dueOn' => null,
                'amount' => null,
                'settled' => $this->signedOn($found->latestContract) !== null,
            ];
        }

        if ($found->latestQuote !== null) {
            $rows[] = [
                'kind' => 'quote',
                'href' => "/projekte/{$found->public_id}/angebot",
                'dueOn' => $found->latestQuote->valid_until?->format('Y-m-d'),
                'acceptedOn' => $found->latestQuote->accepted_at?->format('Y-m-d'),
                'amount' => (int) $found->latestQuote->total,
                'settled' => $found->latestQuote->status === QuoteStatus::Accepted,
            ];
        }

        if ($settings->messagingEnabled()) {
            $rows[] = [
                'kind' => 'messages',
                'href' => "/nachrichten?betrifft={$found->public_id}",
                'dueOn' => null,
                'amount' => null,
                'settled' => true,
            ];
        }

        return $rows;
    }

    private function signedOn(Contract $contract): ?string
    {
        return $contract->signature?->consented_at?->format('Y-m-d');
    }
}
