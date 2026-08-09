<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CheckDateAvailability;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds the inline conflict note under the date field on the capture form.
 * Returns conflicts; it never rejects a date. See CheckDateAvailability.
 */
class AvailabilityController extends Controller
{
    public function __invoke(Request $request, CheckDateAvailability $availability): JsonResponse
    {
        $validated = $request->validate([
            'datum' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'conflicts' => $availability->forDate($validated['datum'] ?? null),
        ]);
    }
}
