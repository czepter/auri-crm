<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\DesignatePrimaryShoot;
use App\Crm\Data\ProjectSuggestionData;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShootController extends Controller
{
    /** The Übersicht's wizard: the project or inquiry first, the shoot is still created on it. */
    public function create(Request $request): Response
    {
        $viewer = $request->user('staff');
        abort_unless($viewer->role->allows(Capability::ManageShoots), 403);

        $project = $request->filled('projekt')
            ? Project::query()->visibleTo($viewer)->where('public_id', $request->query('projekt'))->first()
            : null;

        return Inertia::render('Shoots/Create', [
            'project' => $project ? ProjectSuggestionData::fromModel($project) : null,
            'kinds' => Shoot::kindSuggestions(),
        ]);
    }

    public function store(Request $request, Project $project, AdvanceShootStatus $advance, CrmVocabulary $stages): RedirectResponse
    {
        $this->authorize('manageShoots', $project);

        $validated = $request->validate([
            // Optional, weil die Anfrage-Seite einen Termin ohne Art anlegt:
            // dort ist die Art des Projekts die Art des Termins, wie schon
            // bei der Erfassung. Der Shooting-Tab schickt sie weiterhin.
            'kind' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            // Nullable for the same reason the column is: "Ort noch offen"
            // is a real state, and a required date forces staff to invent one.
            'date' => ['nullable', 'date'],
            'call_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['nullable', 'string', 'max:255'],
            'entered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $shoot = $project->shoots()->create(
            Arr::except($validated, 'entered_at')
            + ['kind' => $validated['kind'] ?? Shoot::kindFor($project->type)]
            + array_filter(['status_entered_at' => $validated['entered_at'] ?? null])
        );

        // BookProject only moves the shoots that exist at booking time.
        if ($project->job()->exists()) {
            $advance(
                $shoot,
                $stages->anchored(StagePipeline::Project, StageAnchor::Booked),
                auth('staff')->user(),
                isset($validated['entered_at']) ? CarbonImmutable::parse($validated['entered_at']) : null,
            );
        }

        // Aus dem Wizard zum neuen Shooting am Projekt, nicht zurück ins leere Formular.
        return $request->boolean('wizard')
            ? redirect('/auftraege/'.$project->public_id.'/shootings')
            : back();
    }

    /**
     * Ein Termin, den es nie gab — der zweite, den jemand versehentlich
     * anlegt. Der Haupttermin bleibt: an ihm hängt, wann das Projekt
     * stattfindet, und ohne ihn hätte der Fall kein Datum mehr.
     */
    public function destroy(Shoot $shoot): RedirectResponse
    {
        $this->authorize('manageShoots', $shoot->project);

        abort_if($shoot->is_primary, 422);

        $shoot->delete();

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
