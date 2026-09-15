<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Documents\Models\DocumentTemplate;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\OverdueReason;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Database\Factories\Payments\PaymentInstallmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One due amount on a PaymentPlan, in minor units — never a float, the same
 * convention App\Quotes\Models\QuoteItem::$unit_price established. No title:
 * mockup 04-auftrag's "Anzahlung" / "2. Rate" / "Restbetrag" labels are
 * derived from position (App\Payments\Data\PaymentPlanData::fromModel())
 * rather than stored, because sort_order already names the same fact a title
 * column would only duplicate.
 */
class PaymentInstallment extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'payment_plan_id', 'amount', 'due_on', 'status', 'sort_order', 'title',
        'settled_via', 'paid_on', 'payment_reference', 'paid_amount', 'overdue_notified_at',
        'overdue_reason', 'overdue_reminders_sent', 'client_reminders_sent',
        'before_due_reminder_sent_at',
        'stripe_checkout_session_id',
        'invoice_number', 'invoiced_at', 'invoice_path', 'invoice_template_id',
        'cancelled_at', 'cancellation_kind', 'cancellation_reason',
        'sent_at', 'viewed_at',
    ];

    protected $attributes = ['status' => PaymentInstallmentStatus::Scheduled, 'sort_order' => 0, 'paid_amount' => 0];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'status' => PaymentInstallmentStatus::class,
            'settled_via' => SettledVia::class,
            'paid_on' => 'date',
            'overdue_notified_at' => 'immutable_datetime',
            'before_due_reminder_sent_at' => 'immutable_datetime',
            'overdue_reason' => OverdueReason::class,
            'invoiced_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'viewed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'cancellation_kind' => CancellationKind::class,
        ];
    }

    /**
     * GoBD Rz. 58/107: the issued document's amount and description are fixed.
     * Here rather than in the writing actions, so a path that grows later
     * inherits the refusal — the due date stays negotiable and is audited.
     */
    protected static function booted(): void
    {
        static::updating(function (self $installment) {
            if ($installment->getOriginal('invoiced_at') === null) {
                return;
            }

            abort_if(
                $installment->isDirty(['amount', 'title']),
                422,
                'An issued invoice keeps its amount and description.',
            );
        });

        static::updated(function (self $installment) {
            if (! $installment->isInvoiced() || ! $installment->wasChanged('due_on')) {
                return;
            }

            AuditLogger::record(
                event: 'payments.installment.due_on_changed',
                actor: auth('staff')->user(),
                context: [
                    'from' => $installment->getOriginal('due_on')?->format('Y-m-d'),
                    'to' => $installment->due_on->format('Y-m-d'),
                ],
                subject: $installment->public_id,
                projectId: $installment->plan?->job?->project_id,
            );
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    /**
     * The exact template version this invoice was rendered from — pinned at
     * issue, never resolved again, so editing the invoice wording later
     * cannot change a document a client already holds.
     */
    public function invoiceTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'invoice_template_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('id');
    }

    /**
     * T-22: still owed and past its date. `invoiced` counts too — an issued,
     * unpaid invoice is where arrears actually start mattering — and the flag
     * is what says the scan has already seen it.
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', PaymentInstallmentStatus::open())
            ->whereNotNull('overdue_notified_at');
    }

    /** #414: what is still owed — what a reminder names and what Stripe charges. */
    public function remaining(): int
    {
        return max(0, (int) $this->amount - (int) $this->paid_amount);
    }

    /** M-06: issued means stamped with a number, whatever the render has done since. */
    public function isInvoiced(): bool
    {
        return $this->invoiced_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * #411: the Endrechnung — the last live installment of its plan by
     * sort_order. § 14 Abs. 5 S. 2 UStG binds this one and no other: it shows
     * the whole supply and deducts the Teilentgelte already taken. A plan with
     * one installment is an Endrechnung with nothing to deduct. A cancelled
     * row is not in the running for it, the same way it is out of every other
     * sum on the plan.
     */
    public function isFinal(): bool
    {
        return $this->live()->last()?->is($this) ?? false;
    }

    /**
     * The Teilentgelte this invoice has to deduct: every live installment
     * before it that has actually been issued. An unissued one took no
     * payment and names no invoice to reference.
     *
     * @return Collection<int, self>
     */
    public function earlierIssued(): Collection
    {
        return $this->live()
            ->filter(fn (self $candidate) => $candidate->sort_order < $this->sort_order && $candidate->isInvoiced())
            ->values();
    }

    /**
     * The accepted quote behind this installment's project, if any — the
     * source of both the rate (InvoiceFigures) and the lines (#411). A
     * revision chain (Q-06) leaves at most one row in `accepted`, so this
     * cannot silently pick between two.
     */
    public function acceptedQuote(): ?Quote
    {
        return $this->plan?->job?->project?->quotes()
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->first();
    }

    /** @return Collection<int, self> */
    private function live(): Collection
    {
        return $this->plan->installments
            ->reject(fn (self $candidate) => $candidate->isCancelled())
            ->sortBy('sort_order')
            ->values();
    }

    protected static function newFactory(): Factory
    {
        return PaymentInstallmentFactory::new();
    }
}
