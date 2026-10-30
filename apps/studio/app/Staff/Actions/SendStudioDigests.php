<?php

namespace App\Staff\Actions;

use App\Models\User;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Models\InAppNotification;
use App\Notifications\NotificationService;
use App\Notifications\Support\NotificationPreferences;
use App\Staff\Notifications\StudioDigest;
use App\Staff\Support\StudioEvents;
use Carbon\CarbonImmutable;

/**
 * T-11's summary, run daily. Whether a person is due is read from their own
 * last send rather than from the calendar — a weekly digest is "seven days
 * since the last one", so a missed run catches up on the next one instead of
 * skipping a week.
 *
 * Only events set to "Zusammenfassung" travel in it: an event still on
 * `sofort` was mailed when it happened, and carrying it again here would
 * announce it twice. T-23 turned that choice into a stored mode, so this
 * reads `digest` rather than inferring it from a silenced mail channel —
 * the morning briefing silences the same channel and means something else.
 */
class SendStudioDigests
{
    private const PERIODS = ['daily' => 1, 'weekly' => 7, 'monthly' => 30];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly NotificationPreferences $preferences,
    ) {}

    /** @return int mails sent */
    public function __invoke(): int
    {
        $sent = 0;

        foreach (User::query()->whereNotNull('digest_frequency')->get() as $user) {
            $days = self::PERIODS[$user->digest_frequency] ?? null;

            if ($days === null) {
                continue;
            }

            $since = $user->digest_sent_at ?? CarbonImmutable::now()->subDays($days);

            if ($user->digest_sent_at !== null && $user->digest_sent_at->addDays($days)->isFuture()) {
                continue;
            }

            $rows = InAppNotification::query()
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->getKey())
                ->where('created_at', '>=', $since)
                ->whereIn('type', $this->digested($user))
                ->orderBy('created_at')
                ->get();

            // Stamped either way: an empty week must not make the next digest
            // reach back two.
            $user->forceFill(['digest_sent_at' => now()])->save();

            if ($rows->isEmpty()) {
                continue;
            }

            $this->notifications->send($user, new StudioDigest($rows));
            $sent++;
        }

        return $sent;
    }

    /**
     * The event classes this person reads in the summary rather than at the
     * moment they happen. T-23 made this a stored mode: "mail off" now also
     * describes the morning briefing, and the two collect different rows.
     *
     * @return list<string>
     */
    private function digested(User $user): array
    {
        return collect(StudioEvents::ALL)
            ->filter(fn (string $class) => $this->preferences->mode($user, $class) === NotificationMode::Digest)
            ->values()
            ->all();
    }
}
