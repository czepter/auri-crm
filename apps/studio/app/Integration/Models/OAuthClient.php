<?php

namespace App\Integration\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * N-04: a client that registered itself over RFC 7591, alongside — never
 * instead of — config('mcp_oauth.client_id')'s well-known one. Public
 * clients only: no secret column, because this flow issues none and PKCE is
 * the credential (see OAuthTokenController). `client_id` is the public
 * identifier callers actually use; `public_id` is the row's, per the house
 * rule, and nothing outside this table reads it yet.
 */
class OAuthClient extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    // Laravel's own guess off this class name is `o_auth_clients`.
    protected $table = 'oauth_clients';

    protected $fillable = ['client_id', 'client_name', 'redirect_uris'];

    protected function casts(): array
    {
        return ['redirect_uris' => 'array'];
    }
}
