<?php

namespace App\Staff\Actions;

use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Settings\Support\StudioSettingsResolver;
use App\Staff\Notifications\StudioSetupPending;

/**
 * The two-day nudge, run daily. Only ever fires once per studio —
 * onboarding_reminder_sent_at is a one-way flag, the same pattern
 * payment_installments.overdue_notified_at already uses.
 */
class RemindOnboarding
{
    public function __construct(
        private readonly StudioSettingsResolver $settings,
        private readonly NotificationService $notifications,
    ) {}

    public function __invoke(): int
    {
        if ($this->settings->onboardingCompleted() || $this->settings->onboardingReminderSent()) {
            return 0;
        }

        $owner = User::query()->where('role', StaffRole::Admin)->oldest('created_at')->first();

        if ($owner === null || $owner->created_at->gt(now()->subDays(2))) {
            return 0;
        }

        $this->notifications->send($owner, new StudioSetupPending($owner->name));
        $this->settings->set('onboarding_reminder_sent_at', now(), null);

        return 1;
    }
}
