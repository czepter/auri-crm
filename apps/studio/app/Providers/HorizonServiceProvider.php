<?php

namespace App\Providers;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Registered for the admin surface only — see bootstrap/providers.php —
 * behind the same capability every other staff-only screen checks. See
 * InvitationController::authorizeStaffManagement() for the sibling.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * The published parent also waves every request through in
     * `app()->environment('local')`, which is exactly the shortcut M-00
     * rules out: a local Compose stack denies a session without ManageStaff
     * the same way production does.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn ($request) => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user) => $user?->role->allows(Capability::ManageStaff) ?? false);
    }
}
