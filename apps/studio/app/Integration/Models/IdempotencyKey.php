<?php

namespace App\Integration\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * I-03: the claim + cached response behind App\Http\Middleware\
 * EnsureIdempotentWrite. See the migration's own docblock for the
 * insert-first race protection this row exists for, and N-04's own
 * migration for why the claim is keyed to a polymorphic `principal` —
 * an ApiToken (I-01) or a staff User authenticated by their own OAuth
 * token (N-04) — rather than api_token_id alone.
 *
 * #395: a claim only replays inside WINDOW_HOURS of its own creation — see
 * EnsureIdempotentWrite's own use of this constant for the matching read
 * side. Prunable sweeps rows the window has already made unreplayable, via
 * `model:prune` in routes/console.php.
 */
class IdempotencyKey extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use Prunable;

    const WINDOW_HOURS = 24;

    protected $fillable = [
        'principal_type',
        'principal_id',
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

    public function principal(): MorphTo
    {
        return $this->morphTo();
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subHours(self::WINDOW_HOURS));
    }
}
