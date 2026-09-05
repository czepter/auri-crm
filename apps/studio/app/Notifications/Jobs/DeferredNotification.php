<?php

namespace App\Notifications\Jobs;

use App\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * T-11's quiet-hours hold. NotificationService::send() dispatches this with
 * ->delay() to the instant App\Notifications\Support\QuietHours::opensAt()
 * names, rather than blocking the request that triggered the send — the
 * same dispatch()->delay() shape every other deferred write in this app
 * uses (see docs/queue.md), not a bespoke scheduler.
 *
 * Re-enters send() rather than calling a transport directly: by the time
 * this runs, quiet hours have passed (that is what the delay was for), so
 * the ordinary path — preferences, transactional exemption, transport
 * fan-out — applies unchanged. A notification only ever gets deferred once:
 * send() only reaches this dispatch when it is currently quiet, and it never
 * will be by the time a job delayed to opensAt() executes.
 *
 * Dispatched onto the `mail` queue (docs/queue.md) — "outbound notifications
 * and transactional mail" is that queue's stated carry, and nothing else has
 * used it yet.
 */
class DeferredNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly mixed $notifiable,
        public readonly Notification $notification,
    ) {
        $this->onQueue('mail');
    }

    public function handle(NotificationService $notifications): void
    {
        $notifications->send($this->notifiable, $this->notification);
    }
}
