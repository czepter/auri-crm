<?php

namespace App\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password reset, which deliberately grants nothing on its own.
 *
 * Resetting proves control of an inbox. The second factor proves possession of
 * the authenticator, and the whole point of requiring both is that either one
 * alone is insufficient — so a reset here never signs anyone in, never clears
 * mfa_secret, and lands on the login page where the ordinary challenge waits.
 */
class PasswordResetController extends Controller
{
    public function request(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker()->sendResetLink($request->only('email'));

        AuditLogger::record(
            event: 'staff.password_reset_requested',
            context: ['email' => $request->input('email'), 'status' => $status],
        );

        // The same answer either way: a differing response tells a stranger
        // which addresses have accounts.
        return back()->with('status', __('auth.reset_sent'));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // mfa_secret and mfa_confirmed_at are untouched on purpose. A
                // reset that cleared them would turn "I forgot my password"
                // into a way around the second factor, which is the one thing
                // this flow must never become.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                AuditLogger::record('staff.password_reset', $user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        // No login here. They go to the form and meet the challenge like any
        // other sign-in.
        return redirect('/login')->with('status', __('auth.reset_done'));
    }
}
