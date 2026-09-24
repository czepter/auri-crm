<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\WriteProjectFieldValues;
use App\Crm\Enums\ProjectFieldType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\ProjectField;
use App\Crm\Models\ProjectType;
use App\Http\Controllers\Controller;
use App\Integration\Data\ProjectReadData;
use App\Integration\Data\ProjectTypeReadData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * C-25 on the api surface, and through it the MCP server: read what a project
 * of a type can carry, write the values, and add a field when the studio asks
 * an agent to.
 *
 * Values go through WriteProjectFieldValues, the same action the admin screen
 * calls, so the definition decides what is storable regardless of the caller.
 * A new definition goes through the same validation the admin store applies,
 * but is written here rather than delegated: ProjectFieldController on the
 * admin side answers with a redirect, which is no use to a JSON client.
 */
class ProjectFieldController extends Controller
{
    public function types(): JsonResponse
    {
        $types = ProjectType::query()->with('fields')->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => ProjectTypeReadData::collect($types->map(ProjectTypeReadData::fromModel(...)))]);
    }

    public function updateValues(Request $request, ClientCase $case, WriteProjectFieldValues $write): JsonResponse
    {
        $validated = $request->validate(['fields' => ['required', 'array']]);

        // Every message the action raises is keyed `fields.<key>`, so a 422
        // names the field that was wrong rather than the request that was.
        $write($case, $validated['fields'], $this->actor($request));

        return response()->json(['data' => ProjectReadData::fromModel($case->fresh())]);
    }

    public function store(Request $request, ProjectType $projectType): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ProjectFieldType::class)],
            'options' => ['array'],
            'options.*' => ['string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:40'],
            'required' => ['boolean'],
            'allow_new_options' => ['boolean'],
        ]);

        $type = ProjectFieldType::from($validated['type']);

        $projectType->fields()->create([
            'key' => ProjectField::keyFor($projectType, $validated['label']),
            'label' => $validated['label'],
            'type' => $type,
            'options' => $type->hasOptions() ? array_values($validated['options'] ?? []) : null,
            'unit' => $type === ProjectFieldType::Number ? ($validated['unit'] ?? null) : null,
            'required' => $request->boolean('required'),
            'allow_new_options' => $type->hasOptions() && $request->boolean('allow_new_options'),
            'sort_order' => (int) $projectType->fields()->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ProjectTypeReadData::fromModel($projectType->fresh()->load('fields'))], 201);
    }

    /** N-04: null for an ApiToken caller, the consenting staff member otherwise — see LeadController::actor(). */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
