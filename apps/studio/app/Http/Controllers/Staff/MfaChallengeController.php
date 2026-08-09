<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class MfaChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('mfa.pending_user_id') === null) {
            return redirect('/login');
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $pendingId = $request->session()->get('mfa.pending_user_id');

        if ($pendingId === null) {
            return redirect('/login');
        }

        $data = $request->validate(['code' => ['required', 'string']]);

        $key = 'staff-mfa|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            AuditLogger::record('staff.mfa.throttled', null);

            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = User::findOrFail($pendingId);

        if (! $this->accepts($user, $data['code'])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            AuditLogger::record('staff.mfa.failed', $user);

            throw ValidationException::withMessages(['code' => __('auth.mfa_invalid')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget('mfa.pending_user_id');

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();
        AuditLogger::record('staff.login.succeeded', $user, ['method' => 'totp']);

        return redirect()->intended('/');
    }

    private function accepts(User $user, string $code): bool
    {
        if ($this->google2fa->verifyKey((string) $user->mfa_secret, $code)) {
            return true;
        }

        $stored = $user->mfa_recovery_codes ?? [];

        $remaining = collect($stored)
            ->reject(fn (string $candidate) => hash_equals($candidate, $code));

        if ($remaining->count() === count($stored)) {
            return false;
        }

        $user->forceFill(['mfa_recovery_codes' => $remaining->values()->all()])->save();
        AuditLogger::record('staff.mfa.recovery_code_used', $user);

        return true;
    }
}
