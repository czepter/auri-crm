<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\StudioClosureData;
use App\Settings\Models\StudioClosure;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-09's Verfügbarkeit pane: the default shoot duration (a StudioSettings
 * column ShootCalendar reads instead of its old hardcoded addHours(2)) and
 * StudioClosure — a date range with a label, CheckDateAvailability's second
 * conflict source. Sits beside StudioSettingsController and
 * NumberRangeController under the same /einstellungen shell and the same
 * ManageSettings gate — see AuthorizesStaffSettings for why that gate is a
 * 404, not a 403.
 */
class StudioClosureController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        $closures = StudioClosure::query()->orderBy('starts_on')->get();

        return Inertia::render('Settings/Availability', [
            'defaultShootDurationMinutes' => $settings->defaultShootDurationMinutes(),
            'closures' => $closures->map(StudioClosureData::fromModel(...))->all(),
        ]);
    }

    /**
     * Its own save action, separate from the closures below: one field with
     * its own "Speichern" button, the same reasoning
     * StudioSettingsController::updateVat gives for splitting Zahlungsdaten
     * & USt from Studio-Profil.
     */
    public function updateDuration(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'default_shoot_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        $settings->set(
            'default_shoot_duration_minutes',
            $validated['default_shoot_duration_minutes'],
            auth('staff')->user(),
        );

        return redirect('/einstellungen/verfuegbarkeit');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $this->validateClosure($request);

        $closure = StudioClosure::create($validated);

        AuditLogger::record(
            event: 'studio_closure.created',
            actor: auth('staff')->user(),
            context: $validated,
            subject: $closure->public_id,
        );

        return redirect('/einstellungen/verfuegbarkeit');
    }

    public function update(Request $request, StudioClosure $studioClosure): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $this->validateClosure($request);

        $studioClosure->update($validated);

        AuditLogger::record(
            event: 'studio_closure.updated',
            actor: auth('staff')->user(),
            context: $validated,
            subject: $studioClosure->public_id,
        );

        return redirect('/einstellungen/verfuegbarkeit');
    }

    public function destroy(StudioClosure $studioClosure): RedirectResponse
    {
        $this->authorizeSettings();

        $label = $studioClosure->label;
        $publicId = $studioClosure->public_id;

        $studioClosure->delete();

        AuditLogger::record(
            event: 'studio_closure.deleted',
            actor: auth('staff')->user(),
            context: ['label' => $label],
            subject: $publicId,
        );

        return redirect('/einstellungen/verfuegbarkeit');
    }

    /** @return array{label: string, starts_on: string, ends_on: string} */
    private function validateClosure(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
    }
}
