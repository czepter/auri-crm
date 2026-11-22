<?php

namespace App\Finance\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Finance\Enums\ExpenseStatus;
use App\Finance\Support\FiscalYearLock;
use Carbon\CarbonInterface;
use Database\Factories\Finance\ExpenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Amounts are cents in `currency`; `gross_eur` is what was actually debited.
 * An expense counts in the year of `paid_at`. The year lock is enforced here
 * and not in the controllers, so no writer can forget it.
 */
class Expense extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'vendor', 'description', 'invoice_no', 'invoice_date', 'paid_at', 'net', 'vat_rate', 'vat', 'gross',
        'currency', 'gross_eur', 'category_id', 'business_share', 'asset_id', 'status', 'note', 'created_by',
    ];

    protected $attributes = ['status' => 'draft', 'currency' => 'EUR', 'business_share' => 100];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'immutable_date',
            'paid_at' => 'immutable_date',
            'net' => 'integer',
            'vat_rate' => 'integer',
            'vat' => 'integer',
            'gross' => 'integer',
            'gross_eur' => 'integer',
            'business_share' => 'integer',
            'status' => ExpenseStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            if ($expense->exists && ! $expense->isDirty()) {
                return;
            }

            FiscalYearLock::assertOpen(
                'paid_at',
                $expense->year(),
                $expense->exists ? self::yearOf($expense->getOriginal('paid_at'), $expense->getOriginal('invoice_date'), $expense->created_at) : null,
            );
        });

        static::deleting(fn (self $expense) => FiscalYearLock::assertOpen('paid_at', $expense->year()));
    }

    public function year(): int
    {
        return self::yearOf($this->paid_at, $this->invoice_date, $this->created_at);
    }

    private static function yearOf(?CarbonInterface $paidAt, ?CarbonInterface $invoiceDate, ?CarbonInterface $createdAt): int
    {
        return ($paidAt ?? $invoiceDate ?? $createdAt ?? now())->year;
    }

    /** Drafts without a payment date fall back to the invoice date, then to the day they were made. */
    public function scopeInYear(Builder $query, int $year): void
    {
        $query->whereRaw('extract(year from coalesce(expenses.paid_at, expenses.invoice_date, expenses.created_at::date)) = ?', [$year]);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(FinanceDocument::class, 'expense_documents', 'expense_id', 'document_id')->withTimestamps();
    }

    protected static function newFactory(): Factory
    {
        return ExpenseFactory::new();
    }
}
