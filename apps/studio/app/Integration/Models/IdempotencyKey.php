<?php

namespace App\Integration\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * I-03: the claim + cached response behind App\Http\Middleware\
 * EnsureIdempotentWrite. See the migration's own docblock for the
 * insert-first race protection this row exists for.
 */
class IdempotencyKey extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = [
        'api_token_id',
        'idempotency_key',
        'request_fingerprint',
        'response_status',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
        ];
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }
}
