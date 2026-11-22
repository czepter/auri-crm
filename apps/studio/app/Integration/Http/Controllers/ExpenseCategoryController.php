<?php

namespace App\Integration\Http\Controllers;

use App\Finance\Data\ExpenseCategoryData;
use App\Finance\Models\ExpenseCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ExpenseCategory::query()->orderBy('sort_order')->get()->map(ExpenseCategoryData::fromModel(...))->all(),
        ]);
    }
}
