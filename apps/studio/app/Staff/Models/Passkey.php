<?php

namespace App\Staff\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passkey extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'staff_passkeys';

    protected $fillable = ['user_id', 'name', 'credential_id', 'source'];

    protected $hidden = ['source'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
