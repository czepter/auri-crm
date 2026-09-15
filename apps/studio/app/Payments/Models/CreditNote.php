<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Documents\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #409. One Gutschrift against one paid installment, in minor units — the
 * same convention PaymentInstallment::$amount keeps. Several per installment
 * are expected: a partial credit note is the normal case, and the sum of
 * them is what IssueCreditNote caps against the amount actually paid.
 *
 * Never negative. The document prints the amount as a deduction; storing it
 * as a negative integer would mean every reader had to remember which sign
 * convention this table chose.
 */
class CreditNote extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = [
        'payment_installment_id', 'number', 'issued_at', 'amount',
        'reason', 'pdf_path', 'template_id', 'stripe_refund_id',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime'];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(PaymentInstallment::class, 'payment_installment_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
