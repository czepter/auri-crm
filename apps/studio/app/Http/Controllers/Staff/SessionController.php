<?php

namespace App\Http\Controllers\Staff;

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
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'staff-login|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            AuditLogger::record('staff.login.throttled', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Try again later.'),
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

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($key);

        if ($user->hasConfirmedMfa()) {
            $request->session()->put('mfa.pending_user_id', $user->id);
            AuditLogger::record('staff.login.mfa_required', $user);

            return redirect('/auth/mfa');
        }

        Auth::guard('staff')->login($user);
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
