<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Http\Controllers\Controller;
use App\Integration\Data\CrewAssignmentReadData;
use App\Integration\Data\StaffReadData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mirrors App\Crm\Http\Controllers\CrewController: the same firstOrCreate
 * on the unique (shoot_id, user_id) index.
 */
class CrewController extends Controller
{
    public function index(Shoot $shoot): JsonResponse
    {
        $crew = $shoot->crew()->with('user')->get()->each->setRelation('shoot', $shoot);

        return response()->json(['data' => $crew->map(CrewAssignmentReadData::fromModel(...))->all()]);
    }

    public function store(Request $request, Shoot $shoot): JsonResponse
    {
        $validated = $request->validate([
            'user' => ['required', 'string', Rule::exists('users', 'public_id')],
        ]);

        $user = User::findByPublicId($validated['user']);

        $assignment = $shoot->crew()->firstOrCreate(['user_id' => $user->id]);
        $assignment->setRelation('shoot', $shoot)->setRelation('user', $user);

        return response()->json(['data' => CrewAssignmentReadData::fromModel($assignment)], 201);
    }

    public function destroy(CrewAssignment $assignment): JsonResponse
    {
        $assignment->delete();

        return response()->json(null, 204);
    }

    public function staff(): JsonResponse
    {
        return response()->json(['data' => User::query()->orderBy('name')->get()->map(StaffReadData::fromModel(...))->all()]);
    }
}
