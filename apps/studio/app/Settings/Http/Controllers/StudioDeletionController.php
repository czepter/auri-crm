<?php

namespace App\Settings\Http\Controllers;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Privacy\Actions\StudioDeletionConfirmation;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * #490: Studio zum Löschen vormerken, bestätigt mit einem Code aus der Mail.
 * Nur der Inhaber; jeder andere bekommt dieselbe 404 wie bei AuthorizesStaffSettings.
 */
class StudioDeletionController extends Controller
{
    public function show(Request $request, StudioDeletionConfirmation $confirmation): Response
    {
        self::authorizeOwner();

        return Inertia::render('Settings/StudioDeletion', [
            'codeSent' => $confirmation->pending(),
            'graceDays' => StudioSettings::DELETION_GRACE_DAYS,
        ]);
    }

    public function code(Request $request, StudioDeletionConfirmation $confirmation): RedirectResponse
    {
        self::authorizeOwner();

        $confirmation->issue($request->user('staff'));

        return back();
    }

    public function store(Request $request, StudioDeletionConfirmation $confirmation): RedirectResponse
    {
        self::authorizeOwner();

        $confirmation->redeem($request->user('staff'), $request->validate(['code' => ['required', 'string']])['code']);
        app(UpdateStudioSetting::class)('deletion_requested_at', now(), $request->user('staff'));

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        self::authorizeOwner();

        app(UpdateStudioSetting::class)('deletion_requested_at', null, $request->user('staff'));

        return back();
    }

    private static function authorizeOwner(): void
    {
        abort_unless(auth('staff')->user()?->role === StaffRole::Admin, 404);
    }
}
