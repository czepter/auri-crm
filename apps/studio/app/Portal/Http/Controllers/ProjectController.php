<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Direct entry to one project. Asks Contact::visibleProjects() the same
 * question the switcher does, so a project the contact is not on and one the
 * studio has not made portal_visible fail identically — firstOrFail() turns
 * either case into a 404 through route-model-binding's usual path, never a
 * 403, because a contact should not be able to tell "not yours" apart from
 * "does not exist".
 *
 * Mockup 06 fills this screen with a hero photo, a derived checklist and a
 * contract; the hero and checklist don't exist yet — they arrive with G-* and
 * E-* — so the screen shows the shoots the studio has chosen to share, plus a
 * link to the contract once M-01 has rendered one, and says nothing it
 * cannot back.
 */
class ProjectController extends Controller
{
    public function __invoke(string $project, StudioSettingsResolver $settings): Response
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('projects.public_id', $project)
            ->with([
                'shoots' => fn ($query) => $query->uncancelled()->orderByRaw('date is null, date'),
                'latestContract',
                'job.paymentPlan',
            ])
            ->firstOrFail();

        return Inertia::render('Projects/Show', [
            'contact' => ['name' => $contact->name],
            // Whether the header offers a way back to the switcher. A
            // contact with only this one project has nowhere else to go, so
            // the link does not pretend otherwise.
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => [
                'id' => $found->public_id,
                'name' => $found->name,
                'type' => $found->type->label(),
                'shoots' => $found->shoots->map(fn ($shoot) => [
                    'id' => $shoot->public_id,
                    'title' => $shoot->label ?? $shoot->kind->label(),
                    'date' => $shoot->date?->format('Y-m-d'),
                    'callTime' => $shoot->call_time === null
                        ? null
                        : substr((string) $shoot->call_time, 0, 5),
                    'venue' => $shoot->venue(),
                    // The client is told where their shoot stands, in the
                    // same words the studio uses. The temperature differs,
                    // not the fact: nothing here is coloured as a warning,
                    // because the studio chases and the client is not
                    // chased in their own portal.
                    'status' => $shoot->status->label(),
                ]),
                // M-01. False before any contract has ever been generated —
                // the link only ever appears once there is something behind it.
                'hasContract' => $found->latestContract !== null,
                // T-07, gated by T-08's own switch (default off): the link
                // is the affordance half of "see payment status", and it
                // must not appear when the switch is off any more than the
                // route behind it should answer — see PaymentController's
                // own `portal.capability:payment_status` middleware.
                'hasPaymentPlan' => $found->job?->paymentPlan !== null && $settings->paymentStatusEnabled(),
                // E-06, gated by T-08's own "messaging" switch (default off)
                // the same way — the affordance half of ThreadController's
                // own `portal.capability:messaging`.
                'messagingEnabled' => $settings->messagingEnabled(),
            ],
        ]);
    }
}
