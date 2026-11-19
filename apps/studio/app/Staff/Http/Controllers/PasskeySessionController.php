<?php

namespace App\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Staff\Support\WebAuthn;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasskeySessionController extends Controller
{
    public function options(WebAuthn $webauthn): JsonResponse
    {
        $options = $webauthn->requestOptions();

        WebAuthn::stash('passkey.request', ['options' => $options]);

        return JsonResponse::fromJsonString($options);
    }

    public function store(Request $request, WebAuthn $webauthn): RedirectResponse
    {
        $data = $request->validate(['credential' => ['required', 'string']]);

        $challenge = WebAuthn::pull('passkey.request');

        $key = 'staff-login|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, SessionController::MAX_ATTEMPTS)) {
            AuditLogger::record('staff.login.throttled', null, ['method' => 'passkey']);

            throw ValidationException::withMessages([
                'credential' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $passkey = $challenge === null ? null : $webauthn->authenticate($challenge['options'], $data['credential']);

        if ($passkey === null) {
            RateLimiter::hit($key, SessionController::DECAY_SECONDS);
            AuditLogger::record('staff.login.failed', null, ['method' => 'passkey']);

            throw ValidationException::withMessages(['credential' => __('auth.passkey_failed')]);
        }

        RateLimiter::clear($key);

        $request->session()->forget(['mfa.pending_user_id', 'mfa.remember']);

        Auth::guard('staff')->login($passkey->user);
        $request->session()->regenerate();
        AuditLogger::record('staff.login.succeeded', $passkey->user, ['method' => 'passkey']);

        return redirect()->intended('/');
    }
}
