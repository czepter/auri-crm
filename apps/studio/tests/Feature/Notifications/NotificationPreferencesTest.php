<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Contracts\Transactional;
use App\Notifications\Jobs\DeferredNotification;
use App\Notifications\Models\InAppNotification;
use App\Notifications\NotificationService;
use App\Notifications\Support\NotificationPreferences;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::store('redis')->flush());

/**
 * T-11: read by App\Notifications\NotificationService::send() rather than by
 * any transport — see that class's own docblock. Mail-only via(), the same
 * shape every real notification in this codebase uses.
 */
class T11DemoNotification extends Notification
{
    public function __construct(private readonly string $message = 'demo') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line($this->message);
    }
}

class T11DemoTransactionalNotification extends Notification implements Transactional
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage;
    }
}

it('suppresses a non-transactional notification whose preference is off, sends no mail, and leaves one audit line', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();

    app(NotificationPreferences::class)->set($user, T11DemoNotification::class, 'mail', false);

    app(NotificationService::class)->send($user, new T11DemoNotification);

    NotificationFacade::assertNothingSent();

    expect(AuditLog::where('event', 'notification.suppressed')->count())->toBe(1);

    // jsonb does not preserve key order — toEqual (loose, order-independent
    // for associative arrays) rather than toBe.
    $row = AuditLog::where('event', 'notification.suppressed')->sole();
    expect($row->context)->toEqual([
        'notification' => T11DemoNotification::class,
        'channel' => 'mail',
        'notifiable_type' => $user->getMorphClass(),
    ])->and($row->subject)->toBe($user->public_id);
});

it('sends normally when no preference row exists — opt-out, not opt-in', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new T11DemoNotification);

    NotificationFacade::assertSentTo($user, T11DemoNotification::class);
    expect(AuditLog::where('event', 'notification.suppressed')->count())->toBe(0);
});

it('never suppresses a Transactional notification, even with a matching preference turned off', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();

    app(NotificationPreferences::class)->set($user, T11DemoTransactionalNotification::class, 'mail', false);

    app(NotificationService::class)->send($user, new T11DemoTransactionalNotification);

    NotificationFacade::assertSentTo($user, T11DemoTransactionalNotification::class);
    expect(AuditLog::where('event', 'notification.suppressed')->count())->toBe(0);
});

it('a wildcard unsubscribe row suppresses every non-transactional class on that channel', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();

    app(NotificationPreferences::class)->set($user, '*', 'mail', false);

    app(NotificationService::class)->send($user, new T11DemoNotification);

    NotificationFacade::assertNothingSent();
});

it('an exact-class preference wins over a wildcard unsubscribe', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();

    app(NotificationPreferences::class)->set($user, '*', 'mail', false);
    app(NotificationPreferences::class)->set($user, T11DemoNotification::class, 'mail', true);

    app(NotificationService::class)->send($user, new T11DemoNotification);

    NotificationFacade::assertSentTo($user, T11DemoNotification::class);
});

it('never suppresses an on-demand address — there is no identity to hold a preference against', function () {
    NotificationFacade::fake();

    app(NotificationService::class)->send(
        NotificationFacade::route('mail', 'nina@nordlicht.test'),
        new T11DemoNotification,
    );

    NotificationFacade::assertSentOnDemand(T11DemoNotification::class);
});

it('holds a non-transactional mail send during quiet hours instead of delivering it, and it arrives once the window opens', function () {
    Date::setTestNow('2026-08-10 02:00:00');
    Queue::fake();
    NotificationFacade::fake();

    StudioSettings::query()->sole()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
    ]);

    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new T11DemoNotification);

    // Not delivered now...
    NotificationFacade::assertNothingSent();

    // ...but re-dispatched for delivery once the window opens, at 07:00 the
    // same tenant-local day — not dropped.
    Queue::assertPushed(DeferredNotification::class, function (DeferredNotification $job) use ($user) {
        return $job->notifiable->is($user)
            && $job->notification instanceof T11DemoNotification
            && $job->delay->equalTo('2026-08-10 07:00:00');
    });

    // Running the job once the window has actually opened delivers it —
    // "arrives when the window opens", the acceptance criterion in full.
    Date::setTestNow('2026-08-10 07:00:01');
    Bus::dispatchNow((new ReflectionClass(DeferredNotification::class))->newInstance($user, new T11DemoNotification));

    NotificationFacade::assertSentTo($user, T11DemoNotification::class);
});

it('never holds a Transactional notification for quiet hours', function () {
    Date::setTestNow('2026-08-10 02:00:00');
    Queue::fake();
    NotificationFacade::fake();

    StudioSettings::query()->sole()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
    ]);

    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new T11DemoTransactionalNotification);

    NotificationFacade::assertSentTo($user, T11DemoTransactionalNotification::class);
    Queue::assertNothingPushed();
});

it('never holds a notification that does not name the mail channel, quiet hours or not', function () {
    Date::setTestNow('2026-08-10 02:00:00');
    Queue::fake();
    NotificationFacade::fake();

    StudioSettings::query()->sole()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
    ]);

    $user = User::factory()->create();

    $notification = new class extends Notification
    {
        /** @return list<string> */
        public function via(object $notifiable): array
        {
            return ['in_app'];
        }

        public function toArray(object $notifiable): array
        {
            return [];
        }
    };

    app(NotificationService::class)->send($user, $notification);

    Queue::assertNothingPushed();
    expect(InAppNotification::count())->toBe(1);
});
