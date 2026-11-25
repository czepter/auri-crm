<?php

namespace App\Staff\Http\Controllers;

use App\Data\AbsenceData;
use App\Data\StaffProfileData;
use App\Enums\AbsenceCategory;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-10: per-person unavailability. The person owns the row — a second
 * shooter reaches this screen and records their own time away without
 * ManageStaff, a different capability answering a different question ("may
 * invite and configure the studio", not "am I away next week"). ManageCrew
 * sees and edits everyone's row here, the same reach it already has over
 * CrewAssignment.
 */
class AbsenceController extends Controller
{
    public function index(): Response
    {
        $viewer = auth('staff')->user();
        $canManageCrew = $viewer->can(Capability::ManageCrew->value);

        $absences = Absence::query()
            ->with('user')
            ->visibleTo($viewer)
            ->orderBy('starts_on')
            ->get();

        return Inertia::render('Staff/Absences', [
            'absences' => LiveUpdate::live($absences->map(fn (Absence $absence) => AbsenceData::fromModel($absence, $viewer))->all()),
            'canManageCrew' => LiveUpdate::live($canManageCrew),
            // Only ManageCrew gets to pick who a row is for — everyone else's
            // only choice is themselves, so the picker is pointless to send.
            'staff' => LiveUpdate::live($canManageCrew
                ? StaffProfileData::collect(User::query()->orderBy('name')->get())
                : []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $viewer = auth('staff')->user();

        $validated = $request->validate([
            // The public id, never the key. Optional: a caller that names
            // nobody is recording their own time away.
            'user' => ['nullable', 'string', Rule::exists('users', 'public_id')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        $subject = isset($validated['user']) ? User::findByPublicId($validated['user']) : $viewer;

        // Your own row needs nothing beyond an account. Anyone else's needs
        // ManageCrew — the same line AbsencePolicy draws for viewing and
        // editing a row that already exists.
        abort_unless($subject->is($viewer) || $viewer->can(Capability::ManageCrew->value), 403);

        $absence = Absence::create([
            'user_id' => $subject->id,
            'category' => AbsenceCategory::Unavailable,
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
        ]);

        AuditLogger::record(
            event: 'absence.created',
            actor: $viewer,
            context: ['user_id' => $subject->public_id],
            subject: $absence->public_id,
        );

        return back();
    }

    public function update(Request $request, Absence $absence): RedirectResponse
    {
        $this->authorize('update', $absence);

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        $absence->update($validated);

        AuditLogger::record(
            event: 'absence.updated',
            actor: auth('staff')->user(),
            context: $validated,
            subject: $absence->public_id,
        );

        return back();
    }

    public function destroy(Absence $absence): RedirectResponse
    {
        $this->authorize('delete', $absence);

        $publicId = $absence->public_id;
        $absence->delete();

        AuditLogger::record('absence.deleted', auth('staff')->user(), [], $publicId);

        return back();
    }
}
