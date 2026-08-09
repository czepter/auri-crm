<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\DesignatePrimaryShoot;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShootController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ShootKind::class)],
            'label' => ['nullable', 'string', 'max:255'],
            // Nullable for the same reason the column is: "Ort noch offen"
            // is a real state, and a required date forces staff to invent one.
            'date' => ['nullable', 'date'],
            'call_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:call_time'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['nullable', 'string', 'max:255'],
        ]);

        $project->shoots()->create($validated);

        return back();
    }

    public function advance(Request $request, Shoot $shoot, AdvanceShootStatus $advance): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ShootStatus::class)],
        ]);

        return $this->attempt('status', fn () => $advance(
            $shoot,
            ShootStatus::from($validated['status']),
            auth('staff')->user(),
        ));
    }

    public function cancel(Request $request, Shoot $shoot, AdvanceShootStatus $advance): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return $this->attempt('reason', fn () => $advance->cancel(
            $shoot,
            $validated['reason'],
            auth('staff')->user(),
        ));
    }

    public function designatePrimary(Shoot $shoot, DesignatePrimaryShoot $designate): RedirectResponse
    {
        return $this->attempt('shoot', fn () => $designate($shoot, auth('staff')->user()));
    }

    /**
     * A rejected domain rule is a validation failure to the studio, not a 500
     * — the screen renders it beside the control they pressed. Same treatment
     * LeadStageController gives a refused stage transition.
     */
    private function attempt(string $field, callable $act): RedirectResponse
    {
        try {
            $act();
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return back();
    }
}
