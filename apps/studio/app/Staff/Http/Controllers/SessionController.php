<?php

namespace App\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 300;

    public function create(): Response|RedirectResponse
    {
        if (! User::query()->exists()) {
            return redirect('/einrichtung');
        }

        // The third of DevLoginController's three guards: false everywhere but
        // local, so the production bundle renders no path to the bypass.
        return Inertia::render('Auth/Login', [
            'devLogin' => app()->environment('local'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);

        $key = 'staff-login|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            AuditLogger::record('staff.login.throttled', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        // Always hash, even for an unknown account, so response timing does
        // not reveal whether the address exists.
        $passwordMatches = Hash::check(
            $credentials['password'],
            $user?->password ?? '$2y$12$uMw29xy3RsSrC1m.HaVdLulVk.HYjAdWq6HMXp1EKLcQJdY2QmGhi',
        );

        if (! $user || ! $passwordMatches) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            AuditLogger::record('staff.login.failed', null, ['email' => $credentials['email']]);

            // auth.failed, not a literal: the sentence is identical for an
            // unknown address and a wrong password, and keying it here keeps
            // that single uniform message in one file rather than inline where
            // a future edit could accidentally make the two cases differ.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);

        if ($user->hasConfirmedMfa()) {
            $request->session()->put('mfa.pending_user_id', $user->id);
            // Die Wahl gehört zur Anmeldung, nicht zum ersten Schritt davon —
            // ohne das fällt sie zwischen Passwort und Code herunter.
            $request->session()->put('mfa.remember', $remember);
            AuditLogger::record('staff.login.mfa_required', $user);

            return redirect('/auth/mfa');
        }

        Auth::guard('staff')->login($user, $remember);
        $request->session()->regenerate();
        AuditLogger::record('staff.login.succeeded', $user, ['method' => 'password']);

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user('staff');

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLogger::record('staff.logout', $user);

        return redirect('/login');
    }
}
