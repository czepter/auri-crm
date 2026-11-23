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

    /** @param  array{kategorie?: ?string, status?: ?string, anteil?: ?string, 'ohne-beleg'?: mixed, vendor?: ?string}  $filters */
    public function scopeFiltered(Builder $query, array $filters): void
    {
        $query
            ->when($filters['kategorie'] ?? null, fn (Builder $q, string $key) => $q->whereHas('category', fn (Builder $c) => $c->where('key', $key)))
            ->when(ExpenseStatus::tryFrom($filters['status'] ?? ''), fn (Builder $q, ExpenseStatus $status) => $q->where('status', $status))
            ->when($filters['anteil'] ?? null, fn (Builder $q, string $share) => match ($share) {
                'voll' => $q->where('business_share', 100),
                'keiner' => $q->where('business_share', 0),
                'anteilig' => $q->whereBetween('business_share', [1, 99]),
                default => $q,
            })
            ->when($filters['ohne-beleg'] ?? null, fn (Builder $q) => $q->whereDoesntHave('documents'))
            ->when($filters['vendor'] ?? null, fn (Builder $q, string $vendor) => $q->where('vendor', 'ilike', '%'.addcslashes($vendor, '%_\\').'%'));
    }

    /** Drafts first, then the latest payments. */
    public function scopeListed(Builder $query): void
    {
        $query->orderByRaw("expenses.status = 'draft' desc")->orderByRaw('expenses.paid_at desc nulls last')->orderByDesc('expenses.id');
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
