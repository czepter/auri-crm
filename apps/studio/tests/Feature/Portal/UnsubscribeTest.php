<?php

use App\Crm\Models\Contact;
use App\Models\AuditLog;
use App\Notifications\NotificationService;
use App\Portal\Notifications\ClientAccessLink;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface('portal'));

it('shows the unsubscribe screen for a real contact, unsubscribed false until confirmed', function () {
    $contact = Contact::factory()->create();

    $props = props("/abmelden/{$contact->public_id}");

    expect($props)
        ->toHaveKey('contact', $contact->public_id)
        ->toHaveKey('unsubscribed', false);
});

it('404s for a public_id that does not belong to a real contact', function () {
    test()->get('/abmelden/not-a-real-contact')->assertNotFound();
});

it('confirming writes a wildcard mail preference, audits it, and the screen reflects it afterwards', function () {
    $contact = Contact::factory()->create();

    test()->post("/abmelden/{$contact->public_id}")->assertRedirect("/abmelden/{$contact->public_id}");

    $row = AuditLog::where('event', 'notification_preference.unsubscribed')->sole();
    expect($row->subject)->toBe($contact->public_id);

    $props = props("/abmelden/{$contact->public_id}");
    expect($props['unsubscribed'])->toBeTrue();
});

it('an unsubscribed contact gets no more QuoteSent mail, and the send is audited as suppressed', function () {
    Notification::fake();
    $contact = Contact::factory()->create();

    test()->post("/abmelden/{$contact->public_id}");

    app(NotificationService::class)->send($contact, new QuoteSent(Quote::factory()->create()));

    Notification::assertNothingSent();
    expect(AuditLog::where('event', 'notification.suppressed')->count())->toBe(1);
});

it('an unsubscribed contact still gets a Transactional ClientAccessLink mail', function () {
    Notification::fake();
    $contact = Contact::factory()->create();

    test()->post("/abmelden/{$contact->public_id}");

    app(NotificationService::class)->send(
        $contact,
        new ClientAccessLink($contact, 'https://example.test/zugang/token'),
    );

    Notification::assertSentTo($contact, ClientAccessLink::class);
});

it('declares no audience on the unsubscribe routes — they are public by design, see AudienceMatrixTest', function () {
    $routes = routesForSurface('portal');

    $route = collect($routes)->firstWhere(fn ($route) => $route->uri() === 'abmelden/{contact}' && in_array('GET', $route->methods(), true));

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeFalse();
});
