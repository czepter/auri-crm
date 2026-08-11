<?php

namespace App\Integration\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Enums\Capability;
use Database\Factories\Integration\ApiTokenFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;

/**
 * A machine credential on the `api` surface (I-01) — G-01's second kind of
 * token, alongside the per-guest ones minted on a Contact. Deliberately its
 * own tokenable (HasApiTokens, same trait Contact uses) rather than a second
 * meaning bolted onto Contact: a script authenticating as the studio itself
 * is not a guest of one project, and giving it a Contact row would mean
 * every future Contact-shaped assumption (a name, an email, a gallery) has
 * to be re-checked against a caller that has none of those. createToken()
 * mints through the exact same hashing Sanctum already does for a guest —
 * see App\Support\PersonalAccessToken, registered once for every tokenable.
 *
 * Scopes are never a vocabulary of their own — see the issue's own framing:
 * a token cannot be granted something no staff role can do. Each entry in
 * `scopes` is therefore required to be a valid Capability case, checked on
 * save, and allows() reads the same way App\Enums\StaffRole::allows() does
 * so the two stay comparable one capability at a time.
 */
class ApiToken extends Model
{
    use BelongsToTenant;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'scopes', 'rate_limit_per_minute'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'rate_limit_per_minute' => 'integer',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $token) {
            foreach ($token->scopes ?? [] as $scope) {
                if (Capability::tryFrom($scope) === null) {
                    throw new InvalidArgumentException("Unknown capability scope \"{$scope}\".");
                }
            }
        });
    }

    public function allows(Capability $capability): bool
    {
        return ! $this->isRevoked() && in_array($capability->value, $this->scopes ?? [], true);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** Cuts every physical token this row ever minted off in one move — a revoked ApiToken authenticates nowhere again. */
    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
        $this->tokens()->delete();
    }

    protected static function newFactory(): Factory
    {
        return ApiTokenFactory::new();
    }
}
