<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-11's Benachrichtigungen pane, the "bald" nav entry every other settings
 * screen has carried since T-01. Quiet hours only: no Notification class in
 * this codebase yet notifies a staff member about a business event (the
 * NotificationService fan-out only carries StaffInvited and
 * ClientAccessLink, both Transactional, and QuoteSent, client-facing) — see
 * the issue's own framing, "read by E-07 rather than by each sender", which
 * is a claim about NotificationService::send() consulting App\Notifications\
 * Support\NotificationPreferences, not a claim that a staff-facing toggle
 * grid exists today. Per-event, per-channel staff preferences are wired
 * end to end and covered in tests/Feature/Notifications/
 * NotificationPreferencesTest.php; this pane grows an "Ereignisse" section
 * with real rows the day the first staff-audience notification ships,
 * rather than a toggle grid with nothing behind it now.
 */
class NotificationSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Notifications', [
            'quietHoursEnabled' => $settings->quietHoursEnabled(),
            'quietHoursStart' => $settings->quietHoursStart(),
            'quietHoursEnd' => $settings->quietHoursEnd(),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'quiet_hours_enabled' => ['required', 'boolean'],
            'quiet_hours_start' => ['nullable', 'required_if:quiet_hours_enabled,true', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'required_if:quiet_hours_enabled,true', 'date_format:H:i'],
        ]);

        $actor = auth('staff')->user();
        $settings->set('quiet_hours_enabled', $validated['quiet_hours_enabled'], $actor);
        $settings->set('quiet_hours_start', $validated['quiet_hours_start'] ?? null, $actor);
        $settings->set('quiet_hours_end', $validated['quiet_hours_end'] ?? null, $actor);

        return redirect('/einstellungen/benachrichtigungen');
    }
}
