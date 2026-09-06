<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserEmailVerification;
use App\Notifications\NotificationService;
use App\Notifications\Studio\StudioEmailVerification;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one door into a database with no staff account yet. EnsureNoStaffExists
 * closes it the moment this succeeds — see that middleware's own doc
 * comment — so there is no "already set up" branch to handle here.
 */
class SetupController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Setup');
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->where('tenant_id', config('tenancy.tenant_id')),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Re-checked inside the lock: two tabs submitting at once must not
        // both become Owner. EnsureNoStaffExists ran before either request
        // reached here and cannot see the other. Postgres's SELECT ... FOR
        // UPDATE only locks rows a query actually returns — on an empty
        // table that's zero rows, so lockForUpdate() here would take no
        // lock at all and let two concurrent requests both pass the
        // emptiness check. An advisory lock on a fixed key has no row to
        // find, so it blocks the second transaction regardless. Session
        // key is arbitrary but stable; pg_advisory_xact_lock releases
        // itself on commit or rollback.
        $user = DB::transaction(function () use ($validated) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('staff_setup')]);

            abort_if(User::query()->exists(), 404);

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => StaffRole::Owner,
            ]);
        });

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        AuditLogger::record('staff.setup_completed', $user);

        $token = bin2hex(random_bytes(32));
        $code = (string) random_int(100000, 999999);

        UserEmailVerification::create([
            'user_id' => $user->id,
            'token_hash' => UserEmailVerification::hash($token),
            'code_hash' => UserEmailVerification::hash($code),
            'expires_at' => now()->addMinutes(UserEmailVerification::VALID_FOR_MINUTES),
        ]);

        $notifications->send($user, new StudioEmailVerification($user->name, $code, url("/e-mail-bestaetigen/{$token}")));

        return redirect('/einrichtung/studio');
    }
}
