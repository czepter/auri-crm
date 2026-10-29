<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Models\StudioClosure;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Data\StudioClosureData;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-09's StudioClosure — a date range with a label, CheckDateAvailability's
 * second conflict source. Listed under Kalender, behind the same
 * ManageSettings gate as every settings screen — see AuthorizesStaffSettings
 * for why that gate is a 404, not a 403.
 */
class StudioClosureController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Closures/Index', [
            'closures' => StudioClosure::query()->orderBy('starts_on')->get()->map(StudioClosureData::fromModel(...))->all(),
        ]);
    }

    // At least one day: an empty selection leaves TaskTemplate::dueFor() nothing to roll onto.
    public function updateWorkingDays(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
        ]);

        app(UpdateStudioSetting::class)('working_days', array_values($validated['working_days']), auth('staff')->user());

        return back();
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

        return back();
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

        return back();
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

        return back();
    }

    /** @return array{label: string, starts_on: string, ends_on: string, message?: ?string} */
    private function validateClosure(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
