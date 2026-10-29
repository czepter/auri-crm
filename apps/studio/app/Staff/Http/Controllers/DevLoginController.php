<?php

namespace App\Staff\Http\Controllers;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A complete authentication bypass: it skips the password and the MFA
 * challenge both. It exists so local inspection does not need an authenticator
 * app, and it must never be reachable anywhere else.
 *
 * Three independent things have to hold for it to run, so no single edit can
 * expose it:
 *
 *  1. routes/admin.php only registers it when the environment is local, so
 *     everywhere else the URL is a 404 with no controller behind it.
 *  2. This method aborts on the same condition, so moving the route out of
 *     that block does not silently arm it.
 *  3. The login page only renders its button on the same condition, so the
 *     built production bundle carries no path to it.
 *
 * The account it signs in gets a random password it never reveals. That keeps
 * it unusable through the normal form even if a local database is copied
 * somewhere it should not be.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $user = User::firstOrCreate(
            ['email' => 'dev@studio.test'],
            [
                'name' => 'Dev Login',
                'password' => Str::random(48),
                'role' => StaffRole::Admin,
                'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                'mfa_confirmed_at' => now(),
                'mfa_recovery_codes' => [],
            ],
        );

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        // Its own method name in the audit log. A dev bypass that records
        // itself as an ordinary password login is how a local convenience
        // becomes invisible in an incident review.
        AuditLogger::record('staff.login.succeeded', $user, ['method' => 'dev_bypass']);

        return redirect()->intended('/');
    }
}
