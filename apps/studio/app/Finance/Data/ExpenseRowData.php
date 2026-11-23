<?php

namespace App\Finance\Data;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExpenseRowData extends Data
{
    public function __construct(
        public string $id,
        public ?string $vendor,
        public ?string $description,
        public ?string $invoiceNo,
        public ?string $invoiceDate,
        public ?string $paidAt,
        public ExpenseStatus $status,
        public ?string $categoryId,
        public ?string $categoryLabel,
        public int $businessShare,
        public string $currency,
        public int $gross,
        public ?int $grossEur,
        public bool $hasReceipt,
        public bool $isAsset,
    ) {}

    public static function fromModel(Expense $expense): self
    {
        return new self(
            id: $expense->public_id,
            vendor: $expense->vendor,
            description: $expense->description,
            invoiceNo: $expense->invoice_no,
            invoiceDate: $expense->invoice_date?->format('Y-m-d'),
            paidAt: $expense->paid_at?->format('Y-m-d'),
            status: $expense->status,
            categoryId: $expense->category?->public_id,
            categoryLabel: $expense->category?->label,
            businessShare: $expense->business_share,
            currency: $expense->currency,
            gross: $expense->gross,
            grossEur: $expense->gross_eur,
            hasReceipt: $expense->documents->isNotEmpty(),
            isAsset: $expense->asset_id !== null,
        );
    }
}
