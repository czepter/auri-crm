<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Documents\Models\DocumentTemplate;
use App\Payments\Enums\OverdueReason;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Support\AuditLogger;
use Database\Factories\Payments\PaymentInstallmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'settled_via', 'paid_on', 'payment_reference', 'overdue_notified_at',
        'overdue_reason', 'overdue_reminders_sent', 'client_reminders_sent',
        'stripe_checkout_session_id',
        'invoice_number', 'invoiced_at', 'invoice_path', 'invoice_template_id',
    ];

    protected $attributes = ['status' => PaymentInstallmentStatus::Scheduled, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'status' => PaymentInstallmentStatus::class,
            'settled_via' => SettledVia::class,
            'paid_on' => 'date',
            'overdue_notified_at' => 'immutable_datetime',
            'overdue_reason' => OverdueReason::class,
            'invoiced_at' => 'immutable_datetime',
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

    /**
     * T-22: still owed and past its date. `invoiced` counts too — an issued,
     * unpaid invoice is where arrears actually start mattering — and the flag
     * is what says the scan has already seen it.
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', [PaymentInstallmentStatus::Scheduled, PaymentInstallmentStatus::Invoiced])
            ->whereNotNull('overdue_notified_at');
    }

    /** M-06: issued means stamped with a number, whatever the render has done since. */
    public function isInvoiced(): bool
    {
        return $this->invoiced_at !== null;
    }

    protected static function newFactory(): Factory
    {
        return PaymentInstallmentFactory::new();
    }
}
