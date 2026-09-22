<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\StaffOwnershipTransferred;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnershipController extends Controller
{
    public function update(
        Request $request,
        User $user,
        NotificationService $notifications,
        StudioSettingsResolver $settings,
    ): RedirectResponse {
        $actor = $request->user('staff');

        abort_unless($actor->role === StaffRole::Admin && ! $user->is($actor), 404);

        $request->validate(['current_password' => ['required', 'current_password:staff']]);

        DB::transaction(function () use ($actor, $user) {
            $rows = User::query()->whereKey([$actor->id, $user->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            // A second transfer that raced this one finds the role already gone.
            abort_unless($rows[$actor->id]->role === StaffRole::Admin, 404);

            $rows[$actor->id]->update(['role' => StaffRole::Manager]);
            $rows[$user->id]->update(['role' => StaffRole::Admin]);

            AuditLogger::record('staff.ownership_transferred', $actor, subject: $user->public_id);
        });

        $actor->refresh();
        $user->refresh();

        foreach ([$actor, $user] as $recipient) {
            $notifications->send($recipient, new StaffOwnershipTransferred($actor, $user, $settings));
        }

        return back();
    }
}
