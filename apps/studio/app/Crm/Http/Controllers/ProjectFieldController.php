<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Enums\ProjectFieldType;
use App\Crm\Models\ProjectField;
use App\Crm\Models\ProjectType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * C-25: the definitions behind cases.field_values, gated like the type they
 * hang off (ProjectTypePolicy). Beside ProjectTypeController rather than under
 * it: a field is its own record with its own order and its own delete rule.
 */
class ProjectFieldController extends Controller
{
    public function store(Request $request, ProjectType $projectType): RedirectResponse
    {
        $this->authorize('update', $projectType);

        $validated = $this->validated($request);

        $projectType->fields()->create($validated + [
            'key' => ProjectField::keyFor($projectType, $validated['label']),
            'sort_order' => (int) $projectType->fields()->max('sort_order') + 1,
        ]);

        return back();
    }

    public function update(Request $request, ProjectType $projectType, ProjectField $projectField): RedirectResponse
    {
        $this->authorize('update', $projectType);

        // No key here, for the reason StageController::update gives: the
        // stored values hang on it, and a rename is a rename of the label.
        $validated = $this->validated($request);

        // The type is what every stored value was written against, so it stops
        // being the studio's to change the moment one exists — the same line
        // destroy() draws, and for the same reason.
        if ($projectField->type !== $validated['type'] && $projectField->standingValues() > 0) {
            throw ValidationException::withMessages([
                'type' => "Feld {$projectField->label} trägt Werte. Typ bleibt.",
            ]);
        }

        $projectField->update($validated);

        return back();
    }

    /** The order IS the order "Details" reads in, so it is stored, not derived. */
    public function reorder(Request $request, ProjectType $projectType): RedirectResponse
    {
        $this->authorize('update', $projectType);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string', 'size:24'],
        ]);

        foreach ($validated['order'] as $position => $publicId) {
            $projectType->fields()->where('public_id', $publicId)->update(['sort_order' => $position]);
        }

        return back();
    }

    public function destroy(ProjectType $projectType, ProjectField $projectField): RedirectResponse
    {
        $this->authorize('update', $projectType);

        $standing = $projectField->standingValues();

        if ($standing > 0) {
            // Same answer ProjectTypeController::destroy gives for a type in
            // use: there is nowhere to move a value to, and dropping it
            // silently loses what the studio wrote down. Deactivating keeps
            // the old projects readable and takes the field off every form.
            throw ValidationException::withMessages([
                'field' => "Feld {$projectField->label} steht auf {$standing} Projekten. Stattdessen deaktivieren.",
            ]);
        }

        $projectField->delete();

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ProjectFieldType::class)],
            'options' => ['array'],
            'options.*' => ['string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:40'],
        ]);

        $type = ProjectFieldType::from($validated['type']);

        // A unit on an Auswahl, or options on a Datum, are settings nothing
        // reads — kept, they would come back the next time the type changes.
        return [
            'label' => $validated['label'],
            'type' => $type,
            'options' => $type->hasOptions() ? array_values($validated['options'] ?? []) : null,
            'unit' => $type === ProjectFieldType::Number ? ($validated['unit'] ?? null) : null,
            'required' => $request->boolean('required'),
            'allow_new_options' => $type->hasOptions() && $request->boolean('allow_new_options'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
