<?php

namespace App\Finance\Http\Controllers;

use App\Concerns\AuthorizesFinances;
use App\Finance\Actions\ChangeFiscalYearVatMode;
use App\Finance\Actions\LockFiscalYear;
use App\Finance\Actions\RenderEuerPdf;
use App\Finance\Models\FiscalYear;
use App\Finance\Support\EuerReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class EuerController extends Controller
{
    use AuthorizesFinances;

    public function show(Request $request, EuerReport $report): Response
    {
        $this->authorizeFinances();

        $year = (int) ($request->validate(['jahr' => ['nullable', 'integer', 'between:2000,2100']])['jahr'] ?? now()->year);
        $data = $report->forYear($year);

        return Inertia::render('Finance/Euer/Show', [
            'report' => $data,
            'years' => FiscalYear::known(),
            'lockable' => $data->lockedAt === null && $year < now()->year,
        ]);
    }

    public function vatMode(Request $request, int $jahr, ChangeFiscalYearVatMode $change): RedirectResponse
    {
        $this->authorizeFinances();

        $change($jahr, $request->validate(['vat_mode' => ['required', 'in:standard,small_business']])['vat_mode'], $request->user('staff'));

        return back();
    }

    public function lock(Request $request, int $jahr, LockFiscalYear $lock): RedirectResponse
    {
        $this->authorizeFinances();

        $lock($jahr, $request->user('staff'));

        return back();
    }

    public function pdf(int $jahr, RenderEuerPdf $render): HttpResponse
    {
        $this->authorizeFinances();

        $fiscal = FiscalYear::query()->where('year', $jahr)->first();
        $bytes = $fiscal?->isLocked() ? $render->stored($fiscal) : $render($jahr);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"euer-{$jahr}.pdf\"",
        ]);
    }
}
