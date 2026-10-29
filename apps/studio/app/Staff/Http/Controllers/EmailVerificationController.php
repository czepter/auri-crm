<?php

namespace App\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserEmailVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function confirm(string $token): RedirectResponse
    {
        $verification = UserEmailVerification::forToken($token);

        if ($verification === null) {
            return redirect('/login')->with('status', __('auth.email_verification_link_dead'));
        }

        $verification->forceFill(['consumed_at' => now()])->save();
        $verification->user->forceFill(['email_verified_at' => now()])->save();

        return redirect('/login')->with('status', 'E-Mail-Adresse bestätigt.');
    }

    public function confirmCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $key = 'email-verification|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = $request->user('staff');
        $verification = UserEmailVerification::forUser($user);

        if ($verification === null
            || $verification->attempts >= UserEmailVerification::MAX_ATTEMPTS
            || ! $verification->matchesCode($data['code'])
        ) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $verification?->increment('attempts');

            throw ValidationException::withMessages(['code' => __('auth.email_verification_invalid')]);
        }

        RateLimiter::clear($key);
        $verification->forceFill(['consumed_at' => now()])->save();
        $user->forceFill(['email_verified_at' => now()])->save();

        return back();
    }
}
