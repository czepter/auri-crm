<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Models\QuoteItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #480: one position of an invoice, in a quote line's shape — net unit price,
 * its own discount, a rate that falls back to the studio's. A position picked
 * from a payment plan names the installment it bills; its gross is that
 * installment's amount, not quantity × price.
 */
class InvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'invoice_id', 'title', 'description', 'quantity', 'unit_price',
        'discount_type', 'discount_value', 'vat_rate', 'payment_installment_id', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['discount_type' => DiscountType::class];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(PaymentInstallment::class, 'payment_installment_id');
    }

    /** The quote's line arithmetic, unsaved: one rounding rule for both documents. */
    public function asQuoteItem(): QuoteItem
    {
        $line = new QuoteItem($this->only(['title', 'description', 'quantity', 'unit_price', 'discount_type', 'discount_value', 'vat_rate']));
        $line->discount_amount = $line->resolveDiscountAmount();

        return $line;
    }
}
