<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Http\Controllers\Controller;
use App\Portal\Support\LegalTexts;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * C-08: the inquiry that reaches the board without a staff member typing it.
 *
 * `routes/api.php` has carried the comment "P1b adds the public inquiry
 * endpoint" since F-02 and P1b closed without it, so every lead in this
 * system has been hand-entered. This is the missing route — and it is only a
 * route: the capture itself is CaptureInquiry, the same action the admin form
 * calls, with the same validation and the same events. A public lead is not a
 * second capture path.
 *
 * `captured_by_id` is already nullable (I-03 dropped its NOT NULL for an
 * ApiToken caller that is not a User), so this story changes no schema at
 * all.
 *
 * ### What this deliberately never answers
 *
 * Every outcome returns the same 202 and the same body. A refused submission
 * — rate-limited, honeypotted, too fast — is indistinguishable from an
 * accepted one, because the alternative is an endpoint that tells the
 * internet which addresses the studio already has on file. That also settles
 * the timing question by construction rather than by measurement: the refusal
 * paths do less work, not different work, and none of them branch on whether
 * the contact exists.
 *
 * **Availability is never revealed.** C-05's inline check tells staff which
 * dates are free; answering that publicly would publish the studio's booked
 * calendar. The form takes a date, it does not answer one.
 *
 * No third-party captcha: it needs a key, a DPA and a consent banner of its
 * own to catch what a honeypot and a rate limit catch. Both signals here are
 * client-supplied and therefore forgeable — they stop the naive traffic that
 * is nearly all of it, and the rate limit is what stops the rest.
 */
class PublicInquiryController extends Controller
{
    /** How long one address, and one IP, must wait before submitting again. */
    private const WINDOW_SECONDS = 900;

    private const IP_ATTEMPTS = 5;

    /** A human has not read, understood and filled a form in under this. */
    private const MIN_ELAPSED_SECONDS = 2;

    public function __invoke(Request $request, CaptureInquiry $capture): JsonResponse
    {
        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::exists('project_types', 'key')->where('is_active', true)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'inquiry_text' => ['nullable', 'string', 'max:10000'],

            // T-02's Datenschutz link is shown beside this box; refusing
            // without it is the one validation error this endpoint is willing
            // to report, because it is a fact about the submission rather
            // than about the studio's data.
            'consent' => ['accepted'],

            // Never filled by a human — the field is hidden in the snippet.
            'website' => ['nullable', 'string', 'max:255'],

            // Seconds since the form rendered, written by the snippet's own
            // inline script. Optional: a client with no JavaScript still gets
            // to send an inquiry.
            'elapsed' => ['nullable', 'integer'],
        ]);

        if ($this->refused($request, $validated)) {
            return $this->accepted();
        }

        $project = $capture(new CaptureInquiryData(
            contact_id: null,
            contact_name: $validated['contact_name'],
            contact_email: $validated['contact_email'],
            contact_phone: $validated['contact_phone'] ?? null,
            // The public form asks for one further name and no way to reach
            // them; the admin's own capture asks for all three (C-09, #244).
            people: filled($validated['partner_name'] ?? null)
                ? [['name' => $validated['partner_name'], 'email' => null, 'phone' => null]]
                : [],
            // A public form should not make a client invent a project name.
            project_name: $validated['project_name'] ?? $validated['contact_name'],
            type: $validated['type'],
            source: LeadSource::tryFrom($validated['source'] ?? '') ?? LeadSource::Website,
            captured_via: CaptureChannel::WebForm,
            date: $validated['date'] ?? null,
            inquiry_text: $validated['inquiry_text'] ?? null,
            referred_by_contact_id: null,
        ), null);

        // The consent itself lives in the append-only audit log rather than a
        // column: "who agreed to what, when, from where" is exactly what that
        // table is for, and a boolean on the lead would record that consent
        // happened without recording what was consented to.
        AuditLogger::record(
            event: 'lead.consent_recorded',
            context: [
                'datenschutz_url' => LegalTexts::url('datenschutz'),
                'channel' => CaptureChannel::WebForm->value,
            ],
            subject: $project->public_id,
            projectId: $project->id,
        );

        return $this->accepted();
    }

    /**
     * Silently, and identically to an acceptance. Every branch here is a
     * reason to drop the submission on the floor, and none of them is a
     * reason to tell the sender which one applied.
     *
     * @param  array<string, mixed>  $validated
     */
    private function refused(Request $request, array $validated): bool
    {
        if (filled($validated['website'] ?? null)) {
            return true;
        }

        if (($validated['elapsed'] ?? null) !== null && $validated['elapsed'] < self::MIN_ELAPSED_SECONDS) {
            return true;
        }

        // Per IP *and* per address. The address limit is what stops one
        // sender flooding from a mobile network's rotating addresses; the IP
        // limit is what stops one address list being walked from one host.
        $byEmail = 'inquiry:email:'.sha1(mb_strtolower($validated['contact_email']));
        $byIp = 'inquiry:ip:'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($byEmail, 1) || RateLimiter::tooManyAttempts($byIp, self::IP_ATTEMPTS)) {
            return true;
        }

        RateLimiter::hit($byEmail, self::WINDOW_SECONDS);
        RateLimiter::hit($byIp, self::WINDOW_SECONDS);

        return false;
    }

    private function accepted(): JsonResponse
    {
        // No id, no name, nothing to correlate. The client is told the
        // studio has it; anything more is a fact the internet does not need.
        return response()->json(['status' => 'received'], 202);
    }
}
