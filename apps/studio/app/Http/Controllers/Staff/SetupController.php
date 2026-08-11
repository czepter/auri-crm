<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\User;
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

    public function store(Request $request): RedirectResponse
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
        // reached here and cannot see the other.
        $user = DB::transaction(function () use ($validated) {
            abort_if(User::query()->lockForUpdate()->exists(), 404);

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

        return redirect('/einrichtung/studio');
    }
}
