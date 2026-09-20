<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Shoot;
use App\Crm\Models\TimelineEntry;
use App\Http\Controllers\Controller;
use App\Integration\Data\TimelineEntryReadData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mirrors App\Crm\Http\Controllers\TimelineController's store and destroy. */
class TimelineController extends Controller
{
    public function index(Shoot $shoot): JsonResponse
    {
        return response()->json(['data' => $shoot->timeline()->orderBy('time')->get()->map(TimelineEntryReadData::fromModel(...))->all()]);
    }

    public function store(Request $request, Shoot $shoot): JsonResponse
    {
        $validated = $request->validate([
            'time' => ['required', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_transit' => ['nullable', 'boolean'],
        ]);

        $entry = $shoot->timeline()->create($validated);

        return response()->json(['data' => TimelineEntryReadData::fromModel($entry)], 201);
    }

    public function destroy(TimelineEntry $entry): JsonResponse
    {
        $entry->delete();

        return response()->json(null, 204);
    }
}
