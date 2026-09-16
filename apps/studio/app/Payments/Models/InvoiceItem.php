<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** #480: one position of an invoice without a project. Gross minor units, rate in basis points. */
class InvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['invoice_id', 'description', 'quantity', 'unit_price', 'vat_rate', 'sort_order'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function total(): int
    {
        return (int) $this->quantity * (int) $this->unit_price;
    }
}
