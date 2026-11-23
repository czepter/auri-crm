<?php

namespace App\Integration\Http\Controllers;

use App\Finance\Support\EuerReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EuerController extends Controller
{
    public function show(int $year, EuerReport $report): JsonResponse
    {
        return response()->json(['data' => $report->forYear($year)]);
    }
}
