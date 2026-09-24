<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Models\DocumentTemplate;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Quotes\Enums\DiscountType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * #480. The document: number, date, recipient, PDF. What the client owes
 * lives on the installments it carries — one or more rates of a project's
 * plan, or for an invoice without a project a single installment outside any
 * plan whose amount is the sum of the items.
 *
 * The status is derived from those installments rather than stored, so a
 * payment booked on a rate can never leave the invoice saying otherwise.
 */
class Invoice extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = [
        'number', 'issued_at', 'intro', 'outro', 'contact_id', 'case_id', 'template_id', 'pdf_path',
        'sent_at', 'viewed_at', 'cancelled_at', 'cancellation_kind', 'cancellation_reason',
        'discount_type', 'discount_value',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'viewed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'cancellation_kind' => CancellationKind::class,
            'discount_type' => DiscountType::class,
        ];
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentInstallment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class);
    }

    /** Pinned at issue, so editing the wording later cannot change a document a client holds. */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function amount(): int
    {
        return (int) $this->installments->sum('amount');
    }

    public function paidAmount(): int
    {
        return (int) $this->installments->sum('paid_amount');
    }

    public function creditedAmount(): int
    {
        return (int) $this->creditNotes->sum('amount');
    }

    public function isPaid(): bool
    {
        return $this->installments->isNotEmpty()
            && $this->installments->every(fn (PaymentInstallment $row) => $row->status === PaymentInstallmentStatus::Paid);
    }

    /** The earliest date still owed, or null once nothing is. */
    public function dueOn(): ?CarbonInterface
    {
        return $this->installments
            ->filter(fn (PaymentInstallment $row) => in_array($row->status, PaymentInstallmentStatus::open(), true))
            ->min('due_on');
    }

    /** cancelled | paid | partial | overdue | open */
    public function status(): string
    {
        return match (true) {
            $this->isCancelled() => 'cancelled',
            $this->isPaid() => 'paid',
            $this->dueOn()?->lt(now('UTC')->startOfDay()) ?? false => 'overdue',
            $this->paidAmount() > 0 => 'partial',
            default => 'open',
        };
    }

    /** The plan the installments belong to — null for an invoice without a project. */
    public function plan(): ?PaymentPlan
    {
        return $this->installments->first()?->plan;
    }
}
