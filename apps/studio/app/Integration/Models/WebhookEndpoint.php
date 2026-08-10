<?php

namespace App\Integration\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Integration\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * I-04: a tenant's registration for outbound webhooks — see the migration's
 * own docblock for why `secret` is plaintext rather than hashed. Created via
 * `webhook-endpoint:create` (no admin screen exists for this yet, the same
 * gap App\Integration\Models\ApiToken already leaves for I-01).
 */
class WebhookEndpoint extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['url', 'secret', 'active', 'subscribed_events'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'subscribed_events' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $endpoint) {
            $endpoint->secret ??= Str::random(40);
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $eventType): bool
    {
        return in_array($eventType, $this->subscribed_events ?? [], true);
    }

    /** HMAC-SHA256 of the raw request body, verifiable on the receiving end with the same secret. */
    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->secret);
    }

    protected static function newFactory(): Factory
    {
        return WebhookEndpointFactory::new();
    }
}
