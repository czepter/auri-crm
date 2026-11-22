<?php

namespace App\Integration\Http\Controllers;

use App\Finance\Data\FixedAssetRowData;
use App\Finance\Models\FixedAsset;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'as_of' => ['nullable', 'date'],
        ]);

        $at = isset($data['as_of']) ? CarbonImmutable::parse($data['as_of']) : null;
        $year = $at?->year ?? (int) ($data['year'] ?? now()->year);

        $assets = FixedAsset::query()->heldIn($year)->with('expense')->orderBy('acquired_at')->orderBy('id')->get();

        return response()->json(['data' => $assets->map(fn (FixedAsset $asset) => FixedAssetRowData::fromModel($asset, $year, $at))->all()]);
    }
}
