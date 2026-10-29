<?php

namespace App\Privacy\Actions;

use App\Models\User;
use App\Models\UserEmailVerification;
use App\Notifications\NotificationService;
use App\Privacy\Notifications\Studio\StudioDeletionCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * #490: der sechsstellige Code, mit dem der Inhaber die Löschung bestätigt.
 * Im Cache nur als Hash, ein Code je Studio: ein neuer ersetzt den alten,
 * eingelöst ist er weg.
 */
class StudioDeletionConfirmation
{
    public const VALID_FOR_MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly NotificationService $notifications) {}

    public function issue(User $owner): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key(), UserEmailVerification::hash($code), now()->addMinutes(self::VALID_FOR_MINUTES));

        $this->notifications->send($owner, new StudioDeletionCode($owner->name, $code));
    }

    public function pending(): bool
    {
        return Cache::has($this->key());
    }

    /** @throws ValidationException */
    public function redeem(User $owner, string $code): void
    {
        $attempts = "studio-deletion|{$owner->id}";

        if (RateLimiter::tooManyAttempts($attempts, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($attempts)]),
            ]);
        }

        $hash = Cache::get($this->key());

        if ($hash === null || ! hash_equals($hash, UserEmailVerification::hash($code))) {
            RateLimiter::hit($attempts, self::VALID_FOR_MINUTES * 60);

            throw ValidationException::withMessages(['code' => __('auth.mfa_invalid')]);
        }

        Cache::forget($this->key());
        RateLimiter::clear($attempts);
    }

    private function key(): string
    {
        return 'studio-deletion-code:'.config('tenancy.tenant_id');
    }
}
