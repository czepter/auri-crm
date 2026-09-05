<?php

namespace App\Integration\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Integration\Enums\WebhookDeliveryStatus;
use Database\Factories\Integration\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * I-04: one attempt cycle of one event against one endpoint — see the
 * migration's own docblock. `payload` is the exact array that was (or will
 * be) JSON-encoded and signed; App\Integration\Jobs\DeliverWebhookDelivery
 * and `webhook:redeliver` both encode it identically
 * (json_encode($delivery->payload)) so the signature is reproducible.
 */
class WebhookDelivery extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'webhook_endpoint_id',
        'event_type',
        'payload',
        'signature',
        'response_status',
        'response_body',
        'attempts',
        'next_retry_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'next_retry_at' => 'immutable_datetime',
            'status' => WebhookDeliveryStatus::class,
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /** The exact bytes to sign and send — recomputed rather than stored, so a stored `payload` is always the single source of truth. */
    public function body(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected static function newFactory(): Factory
    {
        return WebhookDeliveryFactory::new();
    }
}
