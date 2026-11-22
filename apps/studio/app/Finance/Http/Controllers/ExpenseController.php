<?php

namespace App\Finance\Http\Controllers;

use App\Concerns\AuthorizesFinances;
use App\Finance\Actions\DeleteExpense;
use App\Finance\Actions\SaveExpense;
use App\Finance\Actions\SetExpenseStatus;
use App\Finance\Data\ExpenseCategoryData;
use App\Finance\Data\ExpenseDetailData;
use App\Finance\Data\ExpenseRowData;
use App\Finance\Data\ExpenseTotalsData;
use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use App\Finance\Models\FiscalYear;
use App\Finance\Support\AfaCategories;
use App\Finance\Support\Depreciation;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use AuthorizesFinances;

    public function index(Request $request): Response
    {
        $this->authorizeFinances();

        $year = $request->validate(['jahr' => ['nullable', 'integer', 'between:2000,2100']])['jahr'] ?? now()->year;
        $filters = $request->only(['kategorie', 'status', 'anteil', 'ohne-beleg']);

        $totals = ExpenseTotalsData::fromYear(Expense::query()->inYear($year)->get());
        $rows = Expense::query()->inYear($year)->filtered($filters)->with(['category', 'documents'])->listed()->get();

        return Inertia::render('Finance/Expenses/Index', [
            'year' => (int) $year,
            'years' => FiscalYear::known((int) $year),
            'locked' => FiscalYear::query()->where('year', $year)->whereNotNull('locked_at')->exists(),
            'totals' => $totals,
            'filters' => [
                'kategorie' => $filters['kategorie'] ?? null,
                'status' => $filters['status'] ?? null,
                'anteil' => $filters['anteil'] ?? null,
                'ohneBeleg' => (bool) ($filters['ohne-beleg'] ?? false),
            ],
            'categories' => $this->categories(),
            'rows' => $rows->map(ExpenseRowData::fromModel(...))->values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorizeFinances();

        return Inertia::render('Finance/Expenses/Edit', [
            'expense' => null,
            'locked' => false,
            'categories' => $this->categories(),
            ...$this->assetOptions(),
        ]);
    }

    public function show(Expense $expense): Response
    {
        $this->authorizeFinances();

        $expense->load(['category', 'documents', 'asset']);

        return Inertia::render('Finance/Expenses/Edit', [
            'expense' => ExpenseDetailData::fromModel($expense),
            'locked' => FiscalYear::query()->where('year', $expense->year())->whereNotNull('locked_at')->exists(),
            'categories' => $this->categories(),
            ...$this->assetOptions(),
        ]);
    }

    public function store(Request $request, SaveExpense $save, SetExpenseStatus $setStatus): RedirectResponse
    {
        $this->authorizeFinances();

        $book = $request->boolean('book');
        $expense = $save(SaveExpense::validate($request->all(), $book), null, $request->user('staff'));

        if ($book) {
            $setStatus($expense, ExpenseStatus::Booked, $request->user('staff'));
        }

        return redirect()->route('finance.expenses.show', $expense);
    }

    public function update(Request $request, Expense $expense, SaveExpense $save, SetExpenseStatus $setStatus): RedirectResponse
    {
        $this->authorizeFinances();

        $book = $request->boolean('book');
        $save(SaveExpense::validate($request->all(), $book || $expense->status === ExpenseStatus::Booked), $expense, $request->user('staff'));

        if ($book) {
            $setStatus($expense, ExpenseStatus::Booked, $request->user('staff'));
        }

        return redirect()->route('finance.expenses.show', $expense);
    }

    public function status(Request $request, Expense $expense, SetExpenseStatus $setStatus): RedirectResponse
    {
        $this->authorizeFinances();

        $status = ExpenseStatus::from($request->validate(['status' => ['required', 'in:draft,booked,ignored']])['status']);
        $setStatus($expense, $status, $request->user('staff'));

        return back();
    }

    public function destroy(Request $request, Expense $expense, DeleteExpense $delete): RedirectResponse
    {
        $this->authorizeFinances();

        $delete($expense, $request->user('staff'));

        return redirect()->route('finance.expenses.index');
    }

    /** What the form needs to preselect a method: the limit, the useful lives on offer and each year's VAT mode. */
    private function assetOptions(): array
    {
        return [
            'gwgLimit' => Depreciation::GWG_LIMIT,
            'afaCategories' => AfaCategories::options(),
            'vatModes' => (object) FiscalYear::query()->pluck('vat_mode', 'year')->all(),
            'defaultVatMode' => StudioSettings::current()->vat_mode,
        ];
    }

    /** @return list<ExpenseCategoryData> */
    private function categories(): array
    {
        return ExpenseCategory::query()->orderBy('sort_order')->get()->map(ExpenseCategoryData::fromModel(...))->all();
    }
}
