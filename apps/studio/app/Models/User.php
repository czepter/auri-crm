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
}
