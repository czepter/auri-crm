<?php

namespace App\Integration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integration\Data\StudioSettingsReadData;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Settings\Data\NumberRangeData;
use App\Settings\Data\StudioClosureData;
use App\Settings\Models\NumberRange;
use App\Settings\Models\StudioClosure;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\JsonResponse;

/**
 * What create_quote and shoots_in_date_range's callers otherwise had to
 * guess: the studio's own settings, closures and number ranges, each
 * already rendered for the settings screen by StudioSettingsController,
 * StudioClosureController and NumberRangeController — read-only here, the
 * same tenant-scoped rows.
 */
class StudioReadController extends Controller
{
    public function settings(StudioSettingsResolver $settings): JsonResponse
    {
        return response()->json(['data' => StudioSettingsReadData::fromResolver($settings)]);
    }

    public function closures(): JsonResponse
    {
        $closures = StudioClosure::query()->orderBy('starts_on')->get();

        return response()->json(['data' => $closures->map(StudioClosureData::fromModel(...))->all()]);
    }

    public function numberRanges(AllocateDocumentNumber $allocator): JsonResponse
    {
        $ranges = NumberRange::query()->orderBy('document_type')->get();

        return response()->json(['data' => $ranges->map(fn (NumberRange $range) => NumberRangeData::fromModel($range, $allocator))->all()]);
    }
}
