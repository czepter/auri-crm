<?php

namespace App\Finance\Http\Controllers;

use App\Concerns\AuthorizesFinances;
use App\Finance\Actions\ClearDisposal;
use App\Finance\Actions\DisposeFixedAsset;
use App\Finance\Data\FixedAssetRowData;
use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    use AuthorizesFinances;

    public function index(Request $request): Response
    {
        $this->authorizeFinances();

        $year = (int) ($request->validate(['jahr' => ['nullable', 'integer', 'between:2000,2100']])['jahr'] ?? now()->year);

        $assets = FixedAsset::query()
            ->heldIn($year)
            ->with('expense')
            ->orderBy('acquired_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('Finance/Assets/Index', [
            'year' => $year,
            'years' => FiscalYear::known(),
            'locked' => FiscalYear::query()->where('year', $year)->whereNotNull('locked_at')->exists(),
            'rows' => $assets->map(fn (FixedAsset $asset) => FixedAssetRowData::fromModel($asset, $year))->values(),
        ]);
    }

    public function dispose(Request $request, FixedAsset $asset, DisposeFixedAsset $dispose): RedirectResponse
    {
        $this->authorizeFinances();
        abort_unless($asset->expense?->status === ExpenseStatus::Booked, 404);

        $data = $request->validate([
            'disposed_at' => ['required', 'date', 'before_or_equal:today'],
            'disposal_proceeds' => ['required', 'integer', 'min:0'],
        ]);

        $dispose($asset, $data['disposed_at'], $data['disposal_proceeds'], $request->user('staff'));

        return back();
    }

    public function clearDisposal(Request $request, FixedAsset $asset, ClearDisposal $clear): RedirectResponse
    {
        $this->authorizeFinances();

        $clear($asset, $request->user('staff'));

        return back();
    }
}
