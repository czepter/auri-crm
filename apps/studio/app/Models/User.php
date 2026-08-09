<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;
    use Notifiable;

    /**
     * The MFA columns are fillable so factory states and seeders can set them.
     * They are never mass-assigned from request input: every controller that
     * changes them uses forceFill() with an explicit array.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'mfa_secret',
        'mfa_confirmed_at',
        'mfa_recovery_codes',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_confirmed_at' => 'immutable_datetime',
        ];
    }

    public function hasConfirmedMfa(): bool
    {
        return $this->mfa_confirmed_at !== null;
    }

    /**
     * What a crew avatar renders. users carries no avatar column and S-02 does
     * not add one, so the initials are the avatar rather than a fallback for
     * one that failed to load.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $picked = count($words) > 1 ? [$words[0], end($words)] : $words;

        return mb_strtoupper(implode('', array_map(
            fn (string $word) => mb_substr($word, 0, 1),
            $picked,
        )));
    }
}
