<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\CrewAssignment;
use App\Enums\StaffRole;
use App\Notifications\Jobs\SendMailNotification;
use App\Notifications\Models\InAppNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * HasApiTokens (N-04): a staff user can hold their own Sanctum personal
 * access tokens, alongside App\Integration\Models\ApiToken's machine ones —
 * the same tokenable relation, the same api guard, a different kind of
 * caller. See App\Integration\Http\Controllers\OAuthAuthorizationController for
 * where one gets minted and why its abilities can never exceed this user's
 * own StaffRole::capabilities().
 *
 * HasRoles (spatie/laravel-permission) is the actual authority check —
 * policies call $user->can(Capability::X->value). The `role` column stays
 * as the one StaffRole a person carries; booted() below keeps the two in
 * sync so nothing that writes `role` needs to know spatie exists.
 */
class User extends Authenticatable
{
    use BelongsToTenant;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use HasRoles;
    use Notifiable;

    /**
     * Fixed rather than resolved from config('auth.defaults.guard'): that
     * default flips per request (AppServiceProvider calls Auth::shouldUse()
     * per surface), but a User is always staff regardless of which surface
     * is currently handling the request.
     */
    protected $guard_name = 'staff';

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->role instanceof StaffRole && ! $user->hasRole($user->role->value)) {
                $user->syncRoles([$user->role->value]);
            }
        });
    }

    public static function soloStudio(): bool
    {
        return static::query()->count() === 1;
    }

    /**
     * The MFA columns are fillable so factory states and seeders can set them.
     * They are never mass-assigned from request input: every controller that
     * changes them uses forceFill() with an explicit array.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'mfa_secret',
        'mfa_confirmed_at',
        'mfa_recovery_codes',
        'lead_view',
        'digest_frequency',
        'last_quote_action',
    ];

    // calendar_token is a live credential — anyone holding it can read this
    // person's shooting schedule — so it never rides along in a serialised
    // model. The one screen that shows it builds the URL explicitly.
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'calendar_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_confirmed_at' => 'immutable_datetime',
            'digest_sent_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
        ];
    }

    public function hasConfirmedMfa(): bool
    {
        return $this->mfa_confirmed_at !== null;
    }

    /**
     * Eight fresh single-use codes, replacing whatever is left of the old
     * set. Written by enrollment and by the profile screen's "neu erzeugen",
     * and returned rather than read back — the column is encrypted and the
     * codes are shown exactly once.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(): array
    {
        $codes = collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $this->forceFill(['mfa_recovery_codes' => $codes])->save();

        return $codes;
    }

    /**
     * The one mail the framework sends on its own — Password::broker() reaches
     * for this rather than for anything in App\Notifications, so it would
     * otherwise be the single send left going out inside a web request.
     */
    public function sendPasswordResetNotification($token): void
    {
        SendMailNotification::dispatch($this, new ResetPassword($token));
    }

    /** T-11's inbox: what the in_app transport wrote for this person. */
    public function inAppNotifications(): MorphMany
    {
        return $this->morphMany(InAppNotification::class, 'notifiable')->latest();
    }

    public function crewAssignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    /**
     * The calendar subscription secret, minted on first use so an account that
     * never subscribes never carries a live credential.
     */
    public function calendarToken(): string
    {
        if ($this->calendar_token === null) {
            $this->forceFill(['calendar_token' => bin2hex(random_bytes(32))])->save();
        }

        return $this->calendar_token;
    }

    /** Revocation: any subscribed device stops receiving the feed at once. */
    public function regenerateCalendarToken(): string
    {
        $this->forceFill(['calendar_token' => null])->save();

        return $this->calendarToken();
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
