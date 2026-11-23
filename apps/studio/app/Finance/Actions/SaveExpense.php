<?php

namespace App\Finance\Actions;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use App\Finance\Models\FinanceDocument;
use App\Finance\Support\FiscalYearLock;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The one writer of an expense's own fields, for the screen and for the api.
 * It takes the whole record: the api merges a patch over the stored values
 * first, so one set of rules covers both.
 */
class SaveExpense
{
    private const FIELDS = [
        'vendor', 'description', 'invoice_no', 'invoice_date', 'paid_at', 'net', 'vat_rate', 'vat', 'gross',
        'currency', 'gross_eur', 'business_share', 'note',
    ];

    /** Validated input, with `net` and `vat` derived from the gross and rate when the caller gave neither. */
    public static function validate(array $input, bool $booking): array
    {
        $input['currency'] = strtoupper((string) ($input['currency'] ?? 'EUR'));
        $input['vat_rate'] ??= 0;
        $input['business_share'] ??= 100;

        if (! isset($input['gross']) && isset($input['net'], $input['vat'])) {
            $input['gross'] = (int) $input['net'] + (int) $input['vat'];
        }

        if (! isset($input['net'], $input['vat']) && isset($input['gross'])) {
            $rate = (int) $input['vat_rate'];
            $vat = intdiv((int) $input['gross'] * $rate * 2 + (10000 + $rate), (10000 + $rate) * 2);
            $input['vat'] = $vat;
            $input['net'] = (int) $input['gross'] - $vat;
        }

        $required = $booking ? ['required'] : ['nullable'];

        return Validator::make($input, [
            'vendor' => [...$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'paid_at' => [...$required, 'date'],
            'net' => ['required', 'integer', 'min:0'],
            'vat_rate' => ['required', 'integer', 'between:0,10000'],
            'vat' => ['required', 'integer', 'min:0'],
            'gross' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'gross_eur' => ['nullable', 'integer', 'min:0'],
            'category_id' => [...$required, 'string', Rule::exists('expense_categories', 'public_id')->where('tenant_id', config('tenancy.tenant_id'))],
            'business_share' => ['required', 'integer', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:5000'],
            'is_self_issued' => ['sometimes', 'boolean'],
            'document_ids' => ['sometimes', 'array', 'max:20'],
            'document_ids.*' => ['string', 'distinct', Rule::exists('finance_documents', 'public_id')->where('tenant_id', config('tenancy.tenant_id'))],
            'asset' => ['sometimes', 'nullable', 'array'],
            ...SyncExpenseAsset::rules('asset.', is_array($input['asset'] ?? null)),
        ])->after(function ($validator) use ($input) {
            if (! $validator->errors()->hasAny(['net', 'vat', 'gross']) && (int) $input['net'] + (int) $input['vat'] !== (int) $input['gross']) {
                $validator->errors()->add('gross', __('finance.grossMismatch'));
            }

            if (is_array($input['asset'] ?? null)) {
                SyncExpenseAsset::check($validator, $input['asset'], 'asset.');
            }
        })->validate();
    }

    public function __construct(private readonly SyncExpenseAsset $syncAsset) {}

    public function __invoke(array $data, ?Expense $expense = null, ?User $actor = null): Expense
    {
        return DB::transaction(function () use ($data, $expense, $actor) {
            $attributes = Arr::only($data, self::FIELDS);
            $attributes['gross_eur'] = $attributes['currency'] === 'EUR' ? $attributes['gross'] : ($attributes['gross_eur'] ?? null);
            $attributes['category_id'] = isset($data['category_id'])
                ? ExpenseCategory::query()->where('public_id', $data['category_id'])->value('id')
                : null;

            $created = $expense === null;
            $expense ??= new Expense(['status' => ExpenseStatus::Draft, 'created_by' => $actor?->id]);
            $expense->fill($attributes)->save();

            if (array_key_exists('document_ids', $data) || array_key_exists('is_self_issued', $data)) {
                FiscalYearLock::assertOpen('document_ids', $expense->year());
            }

            if (array_key_exists('document_ids', $data)) {
                $expense->documents()->sync(
                    FinanceDocument::query()->whereIn('public_id', $data['document_ids'])->pluck('id')->all(),
                );
            }

            if (array_key_exists('is_self_issued', $data)) {
                FinanceDocument::query()
                    ->whereIn('id', $expense->documents()->pluck('finance_documents.id'))
                    ->update(['is_self_issued' => (bool) $data['is_self_issued']]);
            }

            ($this->syncAsset)($expense, array_key_exists('asset', $data) ? $data['asset'] : false, $actor);

            AuditLogger::record(
                $created ? 'finance.expense.created' : 'finance.expense.updated',
                $actor,
                ['vendor' => $expense->vendor, 'gross' => $expense->gross, 'currency' => $expense->currency],
                $expense->public_id,
            );

            return $expense;
        });
    }
}
