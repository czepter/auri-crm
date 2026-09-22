<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\StaffInvited;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /** The studio's people, and who is still pending. */
    public function index(): Response
    {
        $this->authorizeStaffManagement();

        $viewer = auth('staff')->user();

        return Inertia::render('Staff/Index', [
            'staff' => User::query()->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->public_id,
                'transferable' => $viewer->role === StaffRole::Admin && ! $user->is($viewer),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'roleLabel' => $user->role->label(),
                'mfaConfirmed' => $user->hasConfirmedMfa(),
            ]),
            'invitations' => StaffInvitation::query()->pending()->orderBy('email')->get()
                ->map(fn (StaffInvitation $invitation) => [
                    'id' => $invitation->public_id,
                    'email' => $invitation->email,
                    'name' => $invitation->name,
                    'roleLabel' => $invitation->role->label(),
                    'expiresOn' => $invitation->expires_at->format('Y-m-d'),
                ]),
            'roles' => collect(StaffRole::cases())
                ->map(fn (StaffRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    'capabilities' => array_map(fn (Capability $capability) => $capability->value, $role->capabilities()),
                ])
                ->all(),
            'capabilities' => collect(Capability::cases())
                ->map(fn (Capability $capability) => ['value' => $capability->value, 'label' => $capability->label()])
                ->all(),
        ]);
    }

    public function store(Request $request, StudioSettingsResolver $settings, NotificationService $notifications): RedirectResponse
    {
        $this->authorizeStaffManagement();

        $validated = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->where('tenant_id', config('tenancy.tenant_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(StaffRole::class)],
        ]);

        // The raw token exists here and in the link, and nowhere else.
        $token = bin2hex(random_bytes(32));

        // updateOrCreate on the address: re-inviting replaces the live
        // invitation rather than leaving an older link that still opens.
        $invitation = StaffInvitation::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'role' => $validated['role'],
                'token_hash' => StaffInvitation::hash($token),
                'expires_at' => now()->addDays(StaffInvitation::VALID_FOR_DAYS),
                'invited_by_id' => auth('staff')->id(),
                'accepted_at' => null,
            ],
        );

        // E-07: every send in this app goes through NotificationService —
        // see its own docblock. StaffInvited names only ['mail'] in via(),
        // so this still leaves exactly one email, same as ->notify() did.
        $notifications->send(
            Notification::route('mail', $invitation->email),
            new StaffInvited($invitation, url("/einladung/{$token}"), $settings),
        );

        // The token is deliberately absent from the audit row: an audit log
        // that records live credentials is a second place to steal them from.
        AuditLogger::record(
            event: 'staff.invited',
            actor: auth('staff')->user(),
            context: ['email' => $invitation->email, 'role' => $invitation->role->value],
            subject: $invitation->public_id,
        );

        return back();
    }

    public function destroy(StaffInvitation $invitation): RedirectResponse
    {
        $this->authorizeStaffManagement();

        $invitation->delete();

        AuditLogger::record('staff.invitation_revoked', auth('staff')->user(), [], $invitation->public_id);

        return back();
    }

    /** The invited person's screen. Reached with the token, by someone with no account yet. */
    public function create(string $token): Response|RedirectResponse
    {
        $invitation = StaffInvitation::forToken($token);

        if ($invitation === null) {
            return redirect('/login')->with('status', __('auth.invitation_invalid'));
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'name' => $invitation->name,
            'email' => $invitation->email,
            'roleLabel' => $invitation->role->label(),
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = StaffInvitation::forToken($token);

        if ($invitation === null) {
            return redirect('/login')->with('status', __('auth.invitation_invalid'));
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $invitation->accept($validated['password']);

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        AuditLogger::record('staff.invitation_accepted', $user, [], $invitation->public_id);

        // MFA is opt-in (see RequireStudioOnboarding's own doc comment for
        // the one gate that still runs here) — an invited user lands on the
        // dashboard directly and enrolls later from /auth/mfa/enroll if they
        // choose to.
        return redirect('/');
    }

    private function authorizeStaffManagement(): void
    {
        abort_unless(
            auth('staff')->user()?->can(Capability::ManageStaff->value) ?? false,
            403,
        );
    }
}
