<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Shoot;
use App\Crm\Models\ShotListItem;
use App\Http\Controllers\Controller;
use App\Integration\Data\ShotListItemReadData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mirrors App\Crm\Http\Controllers\ShotListItemController. check() sets
 * checked_off_at rather than toggling it, unlike the admin surface's
 * toggle(): a write with `idempotent` (routes/api.php) must land on the same
 * state on retry, and a toggle would flip back on a second delivery.
 */
class ShotListItemController extends Controller
{
    public function index(Shoot $shoot): JsonResponse
    {
        return response()->json(['data' => $shoot->shotListItems()->get()->map(ShotListItemReadData::fromModel(...))->all()]);
    }

    public function store(Request $request, Shoot $shoot): JsonResponse
    {
        $validated = $request->validate(['label' => ['required', 'string', 'max:255']]);

        $item = $shoot->shotListItems()->create($validated);

        return response()->json(['data' => ShotListItemReadData::fromModel($item)], 201);
    }

    public function check(ShotListItem $item): JsonResponse
    {
        $item->forceFill(['checked_off_at' => $item->checked_off_at ?? now()])->save();

        return response()->json(['data' => ShotListItemReadData::fromModel($item)]);
    }

    public function destroy(ShotListItem $item): JsonResponse
    {
        $item->delete();

        return response()->json(null, 204);
    }
}
