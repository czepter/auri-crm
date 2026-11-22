<?php

namespace App\Finance\Data;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Models\FinanceDocument;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExpenseDetailData extends Data
{
    /** @param  list<FinanceDocumentData>  $documents */
    public function __construct(
        public string $id,
        public ?string $vendor,
        public ?string $description,
        public ?string $invoiceNo,
        public ?string $invoiceDate,
        public ?string $paidAt,
        public ExpenseStatus $status,
        public ?string $categoryId,
        public int $businessShare,
        public string $currency,
        public int $net,
        public int $vatRate,
        public int $vat,
        public int $gross,
        public ?int $grossEur,
        public ?string $note,
        public bool $isSelfIssued,
        public array $documents,
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
            businessShare: $expense->business_share,
            currency: $expense->currency,
            net: $expense->net,
            vatRate: $expense->vat_rate,
            vat: $expense->vat,
            gross: $expense->gross,
            grossEur: $expense->gross_eur,
            note: $expense->note,
            isSelfIssued: $expense->documents->contains(fn (FinanceDocument $document) => $document->is_self_issued),
            documents: $expense->documents->map(FinanceDocumentData::fromModel(...))->values()->all(),
        );
    }
}
