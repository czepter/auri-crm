<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Crm\Models\ProjectType;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What kinds of work the studio offers. The list the capture screen, the
 * project header and the form templates all read from.
 */
class ProjectTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProjectType::class);

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $type = ProjectType::create([
            'key' => $this->key($validated['name']),
            'name' => $validated['name'],
            'sort_order' => (int) ProjectType::query()->max('sort_order') + 1,
        ]);

        AuditLogger::record(
            event: 'project_type.created',
            actor: auth('staff')->user(),
            context: ['type' => $type->key],
            subject: $type->public_id,
        );

        return back();
    }

    public function update(Request $request, ProjectType $projectType): RedirectResponse
    {
        $this->authorize('update', $projectType);

        // No key here, for the reason StageController::update gives.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        DB::transaction(function () use ($projectType, $validated) {
            if ($validated['is_default'] ?? false) {
                ProjectType::query()->whereKeyNot($projectType->getKey())->update(['is_default' => false]);
            }

            $projectType->update($validated);
        });

        return back();
    }

    public function destroy(ProjectType $projectType): RedirectResponse
    {
        $this->authorize('delete', $projectType);

        $standing = ClientCase::query()->where('type', $projectType->key)->count();

        if ($standing > 0) {
            // Unlike a stage, a type has no ordering to move records along,
            // and picking one for them would relabel work the studio priced
            // and shot as something else. Deactivating keeps the existing
            // projects readable and takes it out of every list that offers it.
            throw ValidationException::withMessages([
                'type' => "Typ {$projectType->name} steht auf {$standing} Projekten. Stattdessen deaktivieren.",
            ]);
        }

        AuditLogger::record(
            event: 'project_type.deleted',
            actor: auth('staff')->user(),
            context: ['type' => $projectType->key],
            subject: $projectType->public_id,
        );

        $projectType->delete();

        return back();
    }

    /** A slug of the name, unique and fixed at creation — see StageController::key(). */
    private function key(string $name): string
    {
        $base = str($name)->slug('_')->limit(60, '')->toString() ?: 'typ';
        $key = $base;
        $suffix = 2;

        while (ProjectType::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
