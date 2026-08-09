<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * The idempotency record for one Stripe event ID (M-05). Nothing but
 * App\Payments\Http\Controllers\StripeWebhookController writes one, and it
 * writes exactly once per event ID — the table's own unique constraint on
 * stripe_event_id is what makes a second, concurrent delivery of the same
 * event fail its INSERT rather than race a SELECT-then-INSERT check.
 */
class StripeEvent extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['stripe_event_id', 'type'];
}
