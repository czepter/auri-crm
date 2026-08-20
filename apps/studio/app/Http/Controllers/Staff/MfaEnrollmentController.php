<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class MfaEnrollmentController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user('staff');

        if ($user->hasConfirmedMfa()) {
            return redirect('/');
        }

        // Reusing an existing unconfirmed secret makes a page reload harmless:
        // the QR code the user is midway through scanning stays valid.
        if ($user->mfa_secret === null) {
            $user->forceFill(['mfa_secret' => $this->google2fa->generateSecretKey()])->save();
            AuditLogger::record('staff.mfa.enrollment_started', $user);
        }

        return Inertia::render('Auth/MfaEnroll', [
            'secret' => $user->mfa_secret,
            'otpauthUri' => $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $user->mfa_secret,
            ),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user('staff');
        $data = $request->validate(['code' => ['required', 'string']]);

        abort_if($user->mfa_secret === null, 409, 'Start enrollment first.');

        if (! $this->google2fa->verifyKey($user->mfa_secret, $data['code'])) {
            AuditLogger::record('staff.mfa.enrollment_failed', $user);

            throw ValidationException::withMessages(['code' => __('auth.mfa_invalid')]);
        }

        $user->forceFill(['mfa_confirmed_at' => now()])->save();
        $recoveryCodes = $user->regenerateRecoveryCodes();

        AuditLogger::record('staff.mfa.enrolled', $user);

        // Flashed rather than rendered inline: the codes are shown exactly
        // once, and a reload must not resurrect them.
        return redirect('/auth/mfa/recovery-codes')->with('recovery_codes', $recoveryCodes);
    }

    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if (! is_array($codes)) {
            return redirect('/');
        }

        return Inertia::render('Auth/RecoveryCodes', ['codes' => $codes]);
    }
}
