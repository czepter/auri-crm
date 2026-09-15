<?php

namespace App\Portal\Http\Controllers;

use App\Catalog\Support\PortalOffers;
use App\Contracts\Models\Contract;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\FormInstance;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Portal\Actions\UpdateProjectDetails;
use App\Portal\Support\PortalSettings;
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
    public function __invoke(string $project, StudioSettingsResolver $studio, PortalSettings $settings, PortalOffers $offers): Response
    {
        $contact = auth('portal')->user();

        $found = $this->find($project);
        $editable = $found->clientMayEditDetails();

        $shared = [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            // The same contact block the home screen carries: the person to
            // write to stands on every client screen, so it is composed the
            // same way on all of them.
            'studio' => [
                'name' => $studio->displayName(),
                'messagingEnabled' => $settings->messagingEnabled($contact),
                ...$settings->contactLine(),
            ],
        ];

        return $editable
            ? Inertia::render('Projects/Inquiry', [...$shared, 'project' => $this->inquiry($found)])
            : Inertia::render('Projects/Show', [
                ...$shared,
                'project' => $this->booking($found, $settings),
                // Die Übersicht überspringt, wer genau eine Buchung hat —
                // ohne diese Stelle bliebe „Weitere Leistungen" für den
                // häufigsten Fall unsichtbar.
                'offers' => $offers->for(auth('portal')->user()),
            ]);
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

    /**
     * P-03 (#222): the questionnaires the studio actually shared. Absent
     * until one is — a studio's own checklists live on the same table and
     * are nobody else's business. openCount is what the row says instead of
     * a progress bar: how many questions still have no answer.
     */
    private function questionnaires(Project $found): array
    {
        return FormInstance::query()
            ->shared()
            ->where('project_id', $found->id)
            ->with('fields.currentAnswer')
            ->get()
            ->map(fn (FormInstance $instance) => [
                'id' => $instance->public_id,
                'name' => $instance->name,
                'count' => $instance->fields->count(),
                'openCount' => $instance->fields
                    ->filter(fn ($field) => $field->currentAnswer?->value === null)
                    ->count(),
            ])
            ->all();
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
            'type' => $found->type->name,
            'date' => $shoot?->date?->format('Y-m-d'),
            'venueName' => $shoot?->venue_name,
            'venueCity' => $shoot?->venue_city,
            'inquiryText' => $found->lead?->inquiry_text,
            'questionnaires' => $this->questionnaires($found),
            'contact' => [
                'email' => auth('portal')->user()->email,
                'phone' => auth('portal')->user()->phone,
            ],
        ];
    }

    /** The booked booking: cover, one sentence, pictures, dates, paperwork. */
    private function booking(Project $found, PortalSettings $settings): array
    {
        $found->load([
            'shoots' => fn ($query) => $query->uncancelled()->orderByRaw('date is null, date'),
            'latestContract.signature', 'latestQuote', 'job.paymentPlan.installments',
            'galleries.currentVersion', 'primaryShoot',
        ]);

        $gallery = $found->galleries->first(fn (Gallery $one) => $one->currentVersion !== null);
        $version = $gallery?->currentVersion;

        // The two positions the three states are read against: at or past
        // "fertig" the work is done, past the entry stage it is under way.
        // Positions rather than a list of statuses, so a studio's own extra
        // stage lands in the right state without being named here.
        $stages = app(CrmVocabulary::class);
        $ready = $stages->anchored(StagePipeline::Project, StageAnchor::Ready)?->sort_order;
        $started = $stages->anchored(StagePipeline::Project, StageAnchor::Entry)?->sort_order;

        return [
            'id' => $found->public_id,
            'name' => $found->name,
            'type' => $found->type->name,
            'date' => $found->primaryShoot?->date?->format('Y-m-d'),
            'venue' => $found->primaryShoot?->venue(),
            'shoots' => $found->shoots->map(fn (Shoot $shoot) => [
                'id' => $shoot->public_id,
                'title' => $shoot->label ?? $shoot->kind,
                'date' => $shoot->date?->format('Y-m-d'),
                'callTime' => $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
                'venue' => $shoot->venue(),
                // The client is told where their shoot stands, in the same
                // words the studio uses. The temperature differs, not the
                // fact: nothing here is coloured as a warning, because the
                // studio chases and the client is not chased in their own
                // portal.
                'status' => $shoot->status->name,
                // The same fact as a mark the eye reads before the word.
                // Three states, because colour on this surface is a signal
                // and a fourth would make it decoration again.
                'state' => match (true) {
                    $ready !== null && $shoot->status->sort_order >= $ready => 'done',
                    $started !== null && $shoot->status->sort_order > $started => 'open',
                    default => 'neutral',
                },
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
            'invoices' => $this->invoices($found, $settings),
            'questionnaires' => $this->questionnaires($found),
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
    private function documents(Project $found, PortalSettings $settings): array
    {
        $rows = [];
        $plan = $found->job?->paymentPlan;

        if ($plan !== null && $settings->paymentStatusEnabled(auth('portal')->user())) {
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

        if ($settings->messagingEnabled(auth('portal')->user())) {
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

    /**
     * #415: every invoice this project has issued, newest number last — the
     * client's own copy of the sequence. Behind the same switch the Zahlungen
     * row is, because both lead into the payment screens.
     */
    private function invoices(Project $found, PortalSettings $settings): array
    {
        if (! $settings->paymentStatusEnabled(auth('portal')->user())) {
            return [];
        }

        return ($found->job?->paymentPlan?->installments ?? collect())
            ->filter(fn (PaymentInstallment $one) => $one->isInvoiced())
            ->sortBy('invoice_number')
            ->map(fn (PaymentInstallment $one) => [
                'id' => $one->public_id,
                'number' => $one->invoice_number,
                'issuedOn' => $one->invoiced_at?->format('Y-m-d'),
                'amount' => (int) $one->amount,
                'status' => $one->status->label(),
                'settled' => $one->status === PaymentInstallmentStatus::Paid,
            ])
            ->values()
            ->all();
    }

    private function signedOn(Contract $contract): ?string
    {
        return $contract->signature?->consented_at?->format('Y-m-d');
    }
}
