<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserEmailChange;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Jobs\SendMailNotification;
use App\Notifications\StaffEmailChangeConfirmation;
use App\Notifications\Support\NotificationPreferences;
use App\Notifications\Support\StudioEvents;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The account a staff member holds, as opposed to the studio the settings
 * screen configures — /einstellungen is the studio's, this is the person's.
 * No capability gate: everyone with a session has one of these.
 *
 * Der Kalender-Abo-Link ist eine Pane hier: ein Geheimnis pro Person, kein
 * Studio-Setting.
 *
 * Absences are not a pane here. /abwesenheiten is already per-person and
 * open to every role; the nav links it rather than growing a second screen
 * onto the same table.
 */
class ProfileController extends Controller
{
    public function edit(Request $request, NotificationPreferences $preferences): Response
    {
        $user = $request->user('staff');

        return Inertia::render('Profile/Index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->label(),
            ],
            'pendingEmail' => UserEmailChange::query()->live()
                ->where('user_id', $user->id)
                ->latest()
                ->first()?->new_email,
            'mfaConfirmed' => $user->hasConfirmedMfa(),
            // Nur auf der Kalender-Seite: calendarToken() prägt das Geheimnis
            // beim ersten Aufruf, und /profil ruft jeder auf.
            'calendarFeedUrl' => $request->query('pane') === 'kalender'
                ? url('/kalender/'.$user->calendarToken().'/shootings.ics')
                : null,
            'recoveryCodesLeft' => count($user->mfa_recovery_codes ?? []),
            'digestFrequency' => $user->digest_frequency,
            'events' => collect(StudioEvents::ALL)
                ->map(fn (string $class) => $preferences->mode($user, $class)->value)
                ->all(),
            // T-23: which rows may take the fourth state. The three scans
            // describe a condition that is still true in the morning; the
            // other eight happened at a moment and have the summary already.
            'briefable' => collect(StudioEvents::ALL)
                ->map(fn (string $class) => in_array($class, NotificationMode::BRIEFABLE, true))
                ->all(),
        ]);
    }

    /**
     * The name lands immediately. The e-mail does not: it is the login, so it
     * goes out as a confirmation to the new address and nothing on the user
     * changes until that link is followed — a typo here would lock the only
     * account that could fix it out of the studio.
     */
    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $user = $request->user('staff');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update(['name' => $data['name'], 'phone' => ($data['phone'] ?? '') ?: null]);

        $newEmail = mb_strtolower($data['email']);

        if ($newEmail !== mb_strtolower((string) $user->email)) {
            // Only when the login actually moves: changing it is a credential
            // change, and costs the current password even though the session
            // is already open. Renaming yourself does not.
            $request->validate(['current_password' => ['required', 'current_password:staff']]);

            $this->requestEmailChange($user, $newEmail, $settings);
        }

        return back();
    }

    /**
     * The confirmation link, spent the moment it works. Public by necessity:
     * the person following it is reading the new inbox, which may not be the
     * browser holding the session.
     */
    public function confirmEmail(string $token): RedirectResponse
    {
        $change = UserEmailChange::forToken($token);

        if ($change === null) {
            return redirect('/login')->with('status', __('auth.link_dead'));
        }

        if (User::query()->where('email', $change->new_email)->whereKeyNot($change->user_id)->exists()) {
            return redirect('/login')->with('status', __('auth.email_taken'));
        }

        $change->user->update(['email' => $change->new_email]);
        $change->forceFill(['consumed_at' => now()])->save();

        AuditLogger::record('staff.profile.email_changed', $change->user);

        return redirect('/profil');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user('staff');

        $data = $request->validate([
            'current_password' => ['required', 'current_password:staff'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => $data['password']]);

        AuditLogger::record('staff.profile.password_changed', $user);

        return back();
    }

    /** Fresh codes, and the old set stops working the moment they are shown. */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user('staff');

        abort_unless($user->hasConfirmedMfa(), 409);

        $codes = $user->regenerateRecoveryCodes();

        AuditLogger::record('staff.mfa.recovery_codes_regenerated', $user);

        return redirect('/auth/mfa/recovery-codes')->with('recovery_codes', $codes);
    }

    public function updateNotifications(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        $user = $request->user('staff');

        $data = $request->validate([
            'digest_frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            ...collect(StudioEvents::ALL)
                ->mapWithKeys(fn (string $class, string $key) => [
                    "events.{$key}" => ['required', Rule::in(NotificationMode::allowedFor($class))],
                ])
                ->all(),
        ]);

        $user->update(['digest_frequency' => $data['digest_frequency'] ?? null]);

        foreach (StudioEvents::ALL as $key => $class) {
            $preferences->setMode($user, $class, NotificationMode::from($data['events'][$key]));
        }

        return back();
    }

    private function requestEmailChange(User $user, string $newEmail, StudioSettingsResolver $settings): void
    {
        $token = bin2hex(random_bytes(32));

        UserEmailChange::create([
            'user_id' => $user->id,
            'new_email' => $newEmail,
            'token_hash' => UserEmailChange::hash($token),
            'expires_at' => now()->addHours(UserEmailChange::VALID_FOR_HOURS),
        ]);

        // Addressed to the inbox being claimed, not to the one on file — that
        // is the whole of the proof.
        SendMailNotification::dispatch(
            Notification::route('mail', $newEmail),
            new StaffEmailChangeConfirmation($user, url("/profil/e-mail-bestaetigen/{$token}"), $settings),
        );
    }
}
