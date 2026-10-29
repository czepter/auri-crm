<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Actions\NotifyStudio;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Http\Controllers\Controller;
use App\Notifications\Jobs\SendMailNotification;
use App\Notifications\Support\NotificationPreferences;
use App\Portal\Models\EmailChange;
use App\Portal\Notifications\EmailChangeConfirmation;
use App\Privacy\Models\DsgvoRequest;
use App\Privacy\Notifications\Studio\DsgvoRequested;
use App\Settings\Rules\ValidFormat;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * P-05 (#224). The portal had no account at all: contact details were the
 * studio's to change, access was the studio's to revoke, and subject access
 * lived behind an Admin capability in the admin.
 *
 * Four blocks, and the boundaries between them are the design:
 *
 * `Eure Daten` are always editable, whatever phase a booking is in — those
 * are the client's own particulars, not contract content (which is what P-07
 * locks and P-03 opens).
 *
 * `Post` is the switch /abmelden/{contact} already implements, at the place
 * someone would look for it — and hung off the session here rather than off
 * a public_id in a URL.
 *
 * `Zugang` has no password to manage. What it has is other sessions, and one
 * way to end them.
 *
 * `Datenschutz` asks rather than does. Ten years of retention on signed
 * contracts and issued invoices is why, and the screen says that instead of
 * promising a deletion that would not be one.
 */
class AccountController extends Controller
{
    public function show(NotificationPreferences $preferences): Response
    {
        $contact = auth('portal')->user();

        return Inertia::render('Account', [
            'contact' => [
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'street' => $contact->street,
                'postcode' => $contact->postcode,
                'city' => $contact->city,
            ],
            'pendingEmail' => EmailChange::query()->live()
                ->where('contact_id', $contact->id)
                ->latest()
                ->first()?->new_email,
            'subscribed' => ! $preferences->unsubscribed($contact),
            'openRequests' => DsgvoRequest::query()->open()
                ->where('contact_id', $contact->id)
                ->pluck('kind')
                ->all(),
        ]);
    }

    /**
     * Name, phone and address land immediately. The e-mail does not: it is
     * the login, so it goes out as a confirmation to the new address and
     * nothing on the contact changes until that link is followed.
     */
    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $contact = auth('portal')->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64', new ValidFormat('phone')],
            'street' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('contacts', 'email')->ignore($contact->id),
            ],
        ]);

        $newEmail = mb_strtolower($data['email']);
        unset($data['email']);

        $contact->update($data);

        if ($newEmail !== mb_strtolower((string) $contact->email)) {
            $this->requestEmailChange($contact, $newEmail, $settings);
        }

        return back();
    }

    /**
     * The confirmation link, spent the moment it works — same rule as the
     * magic link it does not otherwise resemble. Public by necessity: the
     * person following it is reading the new inbox, which may not be the
     * browser holding the session.
     */
    public function confirmEmail(Request $request, string $token): RedirectResponse
    {
        $change = EmailChange::forToken($token);

        if ($change === null) {
            return redirect('/anmelden')->with('status', __('portal.link_dead'));
        }

        // Somebody else may have taken the address in the meantime; the
        // client is told to try again rather than shown a database error.
        if (Contact::query()->where('email', $change->new_email)->whereKeyNot($change->contact_id)->exists()) {
            return redirect('/anmelden')->with('status', __('portal.email_taken'));
        }

        $change->contact->update(['email' => $change->new_email]);
        $change->forceFill(['consumed_at' => now()])->save();

        AuditLogger::record('portal.account.email_changed', null, [], $change->contact->public_id);

        return redirect('/einstellungen');
    }

    /**
     * "Sign out on every device". No password to rotate here, and no
     * dependence on the session driver: every live session is refused on its
     * next request because it began before this stamp — see EnsureAudience.
     * This browser included, so it ends on the sign-in screen like any other.
     */
    public function endSessions(Request $request): RedirectResponse
    {
        $contact = auth('portal')->user();

        $contact->forceFill(['sessions_valid_from' => now()])->save();

        AuditLogger::record('portal.account.sessions_ended', null, [], $contact->public_id);

        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/anmelden');
    }

    /** Art. 15 and Art. 17, as requests. Clicking twice is impatience, not a second obligation. */
    public function requestDsgvo(Request $request, NotifyStudio $notifyStudio): RedirectResponse
    {
        $contact = auth('portal')->user();

        $kind = $request->validate([
            'kind' => ['required', Rule::in([DsgvoRequest::EXPORT, DsgvoRequest::ERASURE])],
        ])['kind'];

        $already = DsgvoRequest::query()->open()
            ->where('contact_id', $contact->id)
            ->where('kind', $kind)
            ->exists();

        if (! $already) {
            $dsgvoRequest = DsgvoRequest::create(['contact_id' => $contact->id, 'kind' => $kind]);
            $task = Task::create([
                'title' => "Datenschutz-Anfrage: {$dsgvoRequest->label()}",
                'audience' => TaskAudience::Internal,
                'due_on' => $dsgvoRequest->dueOn(),
                'contact_id' => $contact->id,
            ]);
            $dsgvoRequest->update(['task_id' => $task->id]);

            AuditLogger::record('portal.account.dsgvo_requested', null, ['kind' => $kind], $contact->public_id);

            $notifyStudio(new DsgvoRequested($dsgvoRequest));
        }

        return back();
    }

    /**
     * T-11's switch, at the place a client would go looking for it. Hung off
     * the session rather than off a public_id in a URL — the /abmelden route
     * keeps its own credential for the mail footer that links to it.
     */
    public function updateSubscription(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        $contact = auth('portal')->user();

        $subscribed = $request->validate(['subscribed' => ['required', 'boolean']])['subscribed'];

        $preferences->set($contact, '*', 'mail', $subscribed);

        AuditLogger::record(
            $subscribed ? 'notification_preference.subscribed' : 'notification_preference.unsubscribed',
            null,
            ['channel' => 'mail'],
            $contact->public_id,
        );

        return back();
    }

    private function requestEmailChange(Contact $contact, string $newEmail, StudioSettingsResolver $settings): void
    {
        $token = bin2hex(random_bytes(32));

        EmailChange::create([
            'contact_id' => $contact->id,
            'new_email' => $newEmail,
            'token_hash' => EmailChange::hash($token),
            'expires_at' => now()->addHours(EmailChange::VALID_FOR_HOURS),
        ]);

        // Addressed to the inbox being claimed, not to the one on file —
        // that is the whole of the proof. On-demand rather than through the
        // Contact notifiable, so nothing has to pretend the column already
        // holds the new value.
        SendMailNotification::dispatch(
            Notification::route('mail', $newEmail),
            new EmailChangeConfirmation($contact, url("/e-mail-bestaetigen/{$token}"), $settings),
        );
    }
}
