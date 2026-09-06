<?php

namespace App\Quotes\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Quotes\QuotePaymentStageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Q-11: one share of whatever the quote turns out to be worth. Not an
 * amount — a slider quote has no sum until it is accepted — and not a date:
 * a stage carries a text, and CreatePaymentPlan sets due_on at booking,
 * where a Job and its shoots exist to hang a date off.
 */
class QuotePaymentStage extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['quote_id', 'percent', 'label', 'minimum', 'sort_order'];

    protected $attributes = ['minimum' => 0, 'sort_order' => 0];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    protected static function newFactory(): Factory
    {
        return QuotePaymentStageFactory::new();
    }
}
