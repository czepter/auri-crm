<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use Database\Factories\Payments\PaymentInstallmentFactory;
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
        'payment_plan_id', 'amount', 'due_on', 'status', 'sort_order',
        'settled_via', 'paid_on', 'payment_reference', 'overdue_notified_at',
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
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    protected static function newFactory(): Factory
    {
        return PaymentInstallmentFactory::new();
    }
}
