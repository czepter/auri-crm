<?php

namespace App\Support;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum's own model, scoped and identified the way every other row in this
 * app is (G-01) — see the migration's own note on why, given the tokenable
 * relation already carries the tenant. Registered in AppServiceProvider via
 * Sanctum::usePersonalAccessTokenModel() so every guard built on Sanctum
 * picks this up instead of the package default.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use BelongsToTenant;
    use HasPublicId;
}
