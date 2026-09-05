<?php

use App\Models\User;
use App\Notifications\Models\InAppNotification;
use App\Notifications\NotificationService;
use App\Notifications\Transports\InAppTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

uses(RefreshDatabase::class);

/**
 * A notification that reaches both transports the registry knows about
 * today, so the service's own fan-out — not either transport in isolation —
 * is what most of these specs exercise.
 */
class E07DemoNotification extends Notification
{
    public function __construct(private readonly string $message) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'in_app'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line($this->message);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message];
    }
}

it('fans a notification out to every transport its via() names, tenant-scoped and publicly identified', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new E07DemoNotification('Zahlung überfällig'));

    NotificationFacade::assertSentTo($user, E07DemoNotification::class);

    $row = InAppNotification::sole();
    expect($row->notifiable_id)->toBe($user->id)
        ->and($row->notifiable_type)->toBe($user->getMorphClass())
        ->and($row->type)->toBe(E07DemoNotification::class)
        ->and($row->data)->toBe(['message' => 'Zahlung überfällig'])
        ->and($row->tenant_id)->not->toBeNull()
        ->and($row->public_id)->not->toBeNull()
        ->and($row->read_at)->toBeNull();
});

it('sends only through the channels a notification actually names', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();

    app(NotificationService::class)->send($user, new class extends Notification
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
    });

    expect(InAppNotification::count())->toBe(0);
});

it("routes an on-demand address through the mail transport, so Notification::fake()'s assertSentOnDemand keeps working", function () {
    // Mail-only via(), the same shape every real notification in this
    // codebase uses today (StaffInvited, ClientAccessLink, QuoteSent) — an
    // on-demand address carries no persisted identity for InAppTransport to
    // key a row on, so a notification sent to one must not name 'in_app'.
    NotificationFacade::fake();

    $notification = new class extends Notification
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
    };

    app(NotificationService::class)->send(
        NotificationFacade::route('mail', 'nina@nordlicht.test'),
        $notification,
    );

    NotificationFacade::assertSentOnDemand($notification::class);
    expect(InAppNotification::count())->toBe(0);
});

it('refuses to write an in-app row for a notifiable with no persisted identity', function () {
    app(InAppTransport::class)->send(new AnonymousNotifiable, new E07DemoNotification('x'));
})->throws(RuntimeException::class);
