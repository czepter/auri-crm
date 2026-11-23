<?php

namespace App\Integration\Http\Controllers;

use App\Finance\Actions\FindDuplicateExpenses;
use App\Finance\Actions\SaveExpense;
use App\Finance\Actions\SyncExpenseAsset;
use App\Finance\Data\ExpenseDetailData;
use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Expenses on the api surface, drafts only. Nothing here can set a status, so
 * an expense is booked, ignored, locked or deleted by a person in the screens
 * and never through this door.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'status' => ['nullable', 'in:draft,booked,ignored'],
            'category' => ['nullable', 'string', 'max:50'],
            'vendor' => ['nullable', 'string', 'max:200'],
            'without_receipt' => ['nullable', 'in:true,false'],
        ]);

        $expenses = Expense::query()
            ->when($filters['year'] ?? null, fn ($query, int $year) => $query->inYear($year))
            ->filtered([
                'kategorie' => $filters['category'] ?? null,
                'status' => $filters['status'] ?? null,
                'vendor' => $filters['vendor'] ?? null,
                'ohne-beleg' => ($filters['without_receipt'] ?? 'false') === 'true',
            ])
            ->with(['category', 'documents', 'asset'])
            ->listed()
            ->limit(200)
            ->get();

        return response()->json(['data' => $expenses->map(ExpenseDetailData::fromModel(...))->all()]);
    }

    public function duplicates(Request $request, FindDuplicateExpenses $find): JsonResponse
    {
        $data = $request->validate([
            'sha256' => ['nullable', 'regex:/^[0-9a-f]{64}$/'],
            'vendor' => ['nullable', 'string', 'max:200'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'gross' => ['nullable', 'integer', 'min:0'],
            'invoice_date' => ['nullable', 'date'],
        ]);

        $found = $find($data['sha256'] ?? null, $data['vendor'] ?? null, $data['invoice_no'] ?? null, $data['gross'] ?? null, $data['invoice_date'] ?? null);

        return response()->json(['data' => $found->load('asset')->map(ExpenseDetailData::fromModel(...))->all()]);
    }

    public function store(Request $request, SaveExpense $save): JsonResponse
    {
        $expense = $save(SaveExpense::validate(Arr::except($request->all(), ['asset', 'status']), false), null, $this->actor($request));

        return $this->answer($expense, 201);
    }

    public function update(Request $request, Expense $expense, SaveExpense $save): JsonResponse
    {
        $this->assertDraft($expense);

        $patch = Arr::except($request->all(), ['asset', 'status']);
        $current = [
            ...$expense->only(['vendor', 'description', 'invoice_no', 'net', 'vat_rate', 'vat', 'gross', 'currency', 'gross_eur', 'business_share', 'note']),
            'invoice_date' => $expense->invoice_date?->format('Y-m-d'),
            'paid_at' => $expense->paid_at?->format('Y-m-d'),
            'category_id' => $expense->category?->public_id,
        ];

        // A new gross or rate re-derives net and VAT; new net and VAT re-derive the gross.
        if (Arr::hasAny($patch, ['gross', 'vat_rate']) && ! Arr::hasAny($patch, ['net', 'vat'])) {
            unset($current['net'], $current['vat']);
        } elseif (Arr::hasAny($patch, ['net', 'vat']) && ! array_key_exists('gross', $patch)) {
            unset($current['gross']);
        }

        $data = SaveExpense::validate([...$current, ...$patch], false);

        return $this->answer($save($data, $expense, $this->actor($request)));
    }

    public function asset(Request $request, Expense $expense): JsonResponse
    {
        $this->assertDraft($expense);

        $base = $expense->asset
            ? [
                'name' => $expense->asset->name,
                'acquired_at' => $expense->asset->acquired_at->format('Y-m-d'),
                'cost_net' => $expense->asset->cost_net,
                'cost_gross' => $expense->asset->cost_gross,
                'method' => $expense->asset->method->value,
                'useful_life_months' => $expense->asset->useful_life_months,
                'afa_category' => $expense->asset->afa_category,
            ]
            : SyncExpenseAsset::defaults($expense);

        $asset = [...$base, ...array_filter(
            Arr::only($request->all(), ['name', 'acquired_at', 'cost_net', 'cost_gross', 'method', 'useful_life_months', 'afa_category']),
            fn ($value) => $value !== null,
        )];

        $validator = Validator::make($asset, SyncExpenseAsset::rules('', true))
            ->after(fn ($validator) => SyncExpenseAsset::check($validator, $asset, ''));
        app(SyncExpenseAsset::class)($expense, $validator->validate(), $this->actor($request));

        return $this->answer($expense->refresh());
    }

    private function assertDraft(Expense $expense): void
    {
        if ($expense->status !== ExpenseStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('finance.notDraft')]);
        }
    }

    private function answer(Expense $expense, int $status = 200): JsonResponse
    {
        return response()->json(['data' => ExpenseDetailData::fromModel($expense->load(['category', 'documents', 'asset']))], $status);
    }

    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
