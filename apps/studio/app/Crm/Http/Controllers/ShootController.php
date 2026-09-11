<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\DesignatePrimaryShoot;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShootController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageShoots', $project);

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
            'entered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $project->shoots()->create(Arr::except($validated, 'entered_at') + array_filter(['status_entered_at' => $validated['entered_at'] ?? null]));

        return back();
    }

    public function advance(Request $request, Shoot $shoot, AdvanceShootStatus $advance, CrmVocabulary $stages): RedirectResponse
    {
        $this->authorize('update', $shoot);

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Project->value)->where('is_active', true),
            ],
            'entered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->attempt('status', fn () => $advance(
            $shoot,
            $stages->byKey(StagePipeline::Project, $validated['status']),
            auth('staff')->user(),
            isset($validated['entered_at']) ? CarbonImmutable::parse($validated['entered_at']) : null,
        ));
    }

    public function cancel(Request $request, Shoot $shoot, AdvanceShootStatus $advance): RedirectResponse
    {
        $this->authorize('update', $shoot);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return $this->attempt('reason', fn () => $advance->cancel(
            $shoot,
            $validated['reason'],
            auth('staff')->user(),
        ));
    }

    /** Das Gegenstück zu cancel(): ein irrtümlich abgesagtes Shooting steht wieder in seinem Status. */
    public function uncancel(Shoot $shoot, AdvanceShootStatus $advance): RedirectResponse
    {
        $this->authorize('update', $shoot);

        return $this->attempt('reason', fn () => $advance->uncancel($shoot, auth('staff')->user()));
    }

    public function designatePrimary(Shoot $shoot, DesignatePrimaryShoot $designate): RedirectResponse
    {
        $this->authorize('update', $shoot);

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
