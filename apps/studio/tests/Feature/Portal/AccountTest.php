<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Notifications\Studio\DsgvoRequested;
use App\Portal\Models\DsgvoRequest;
use App\Portal\Models\EmailChange;
use App\Portal\Notifications\EmailChangeConfirmation;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

it('lets the client change their own details, whatever phase anything is in', function () {
    $contact = Contact::factory()->create(['name' => 'Lena Meier', 'phone' => null]);

    test()->actingAs($contact, 'portal')
        ->patch('/einstellungen', [
            'name' => 'Lena Meier-Kowalski',
            'email' => $contact->email,
            'phone' => '0171 2223344',
            'street' => 'Elbchaussee 12',
            'postcode' => '22765',
            'city' => 'Hamburg',
        ])
        ->assertRedirect();

    expect($contact->fresh()->name)->toBe('Lena Meier-Kowalski')
        ->and($contact->fresh()->city)->toBe('Hamburg');
});

// The address is the login. A typo in it would lock the client out of the
// only way back in, so nothing is written until the new inbox answers.
it('never writes a new e-mail before the new address confirms it', function () {
    Notification::fake();

    $contact = Contact::factory()->create(['email' => 'alt@example.test']);

    test()->actingAs($contact, 'portal')
        ->patch('/einstellungen', ['name' => $contact->name, 'email' => 'neu@example.test'])
        ->assertRedirect();

    expect($contact->fresh()->email)->toBe('alt@example.test')
        ->and(EmailChange::query()->live()->where('contact_id', $contact->id)->count())->toBe(1);

    Notification::assertSentOnDemand(
        EmailChangeConfirmation::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'neu@example.test',
    );
});

it('swaps the address once the link is followed, and spends the link', function () {
    $contact = Contact::factory()->create(['email' => 'alt@example.test']);

    $token = bin2hex(random_bytes(32));
    EmailChange::create([
        'contact_id' => $contact->id,
        'new_email' => 'neu@example.test',
        'token_hash' => EmailChange::hash($token),
        'expires_at' => now()->addDay(),
    ]);

    test()->get("/e-mail-bestaetigen/{$token}")->assertRedirect('/einstellungen');

    expect($contact->fresh()->email)->toBe('neu@example.test');

    // Single use, same rule the magic link follows.
    test()->get("/e-mail-bestaetigen/{$token}")->assertRedirect('/anmelden');
});

it('refuses an address someone else claimed in the meantime', function () {
    $contact = Contact::factory()->create(['email' => 'alt@example.test']);
    Contact::factory()->create(['email' => 'neu@example.test']);

    $token = bin2hex(random_bytes(32));
    EmailChange::create([
        'contact_id' => $contact->id,
        'new_email' => 'neu@example.test',
        'token_hash' => EmailChange::hash($token),
        'expires_at' => now()->addDay(),
    ]);

    test()->get("/e-mail-bestaetigen/{$token}")->assertRedirect('/anmelden');

    expect($contact->fresh()->email)->toBe('alt@example.test');
});

// "Sign out on every device", without a password to rotate and without
// depending on the session driver.
it('turns away a session that began before the client ended their sessions', function () {
    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')
        ->withSession(['portal_session_started_at' => now()->subHour()->timestamp])
        ->get('/')
        ->assertOk();

    $contact->forceFill(['sessions_valid_from' => now()])->save();

    test()->actingAs($contact, 'portal')
        ->withSession(['portal_session_started_at' => now()->subHour()->timestamp])
        ->get('/')
        ->assertRedirect('/anmelden');
});

it('keeps a session that began after the stamp', function () {
    $contact = Contact::factory()->create(['sessions_valid_from' => now()->subHour()]);

    test()->actingAs($contact, 'portal')
        ->withSession(['portal_session_started_at' => now()->timestamp])
        ->get('/')
        ->assertOk();
});

it('records a subject-access request once, however often it is clicked', function () {
    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')->post('/einstellungen/datenschutz', ['kind' => 'export']);
    test()->post('/einstellungen/datenschutz', ['kind' => 'export']);

    expect(DsgvoRequest::query()->open()->where('contact_id', $contact->id)->count())->toBe(1);
});

it('keeps the deletion request separate from the data copy', function () {
    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')->post('/einstellungen/datenschutz', ['kind' => 'export']);
    test()->post('/einstellungen/datenschutz', ['kind' => 'erasure']);

    expect(DsgvoRequest::query()->open()->pluck('kind')->all())
        ->toEqualCanonicalizing(['export', 'erasure']);
});

it('rejects a request kind nobody offers', function () {
    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')
        ->post('/einstellungen/datenschutz', ['kind' => 'alles-loeschen'])
        ->assertSessionHasErrors('kind');
});

it('turns the mail switch off and on from the account screen', function () {
    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')->post('/einstellungen/post', ['subscribed' => false]);

    expect(props('/einstellungen')['subscribed'])->toBeFalse();

    test()->post('/einstellungen/post', ['subscribed' => true]);

    expect(props('/einstellungen')['subscribed'])->toBeTrue();
});

// Doing the thing is what closes the obligation — a separate "mark handled"
// button would be a step someone forgets, and then the open list lies.
it('closes the request when the studio answers it', function () {
    useSurface(Surface::ADMIN);

    $contact = Contact::factory()->create();
    DsgvoRequest::create(['contact_id' => $contact->id, 'kind' => DsgvoRequest::EXPORT]);

    test()->actingAs(staffMember(App\Enums\StaffRole::Admin), 'staff')
        ->get("/kontakte/{$contact->public_id}/dsgvo/export")
        ->assertOk();

    expect(DsgvoRequest::query()->open()->count())->toBe(0);
});

it('hands the studio a mail and a task due one month after the request', function () {
    Notification::fake();
    $staff = staffMember();
    $contact = Contact::factory()->create();

    test()->travelTo('2026-01-31 10:00');
    test()->actingAs($contact, 'portal')->post('/einstellungen/datenschutz', ['kind' => 'export']);
    test()->post('/einstellungen/datenschutz', ['kind' => 'export']);

    $task = Task::sole();

    expect($task->title)->toBe('Datenschutz-Anfrage: Datenkopie')
        ->and($task->case_id)->toBeNull()
        ->and($task->contact_id)->toBe($contact->id)
        ->and($task->due_on->toDateString())->toBe('2026-02-28')
        ->and(DsgvoRequest::sole()->task_id)->toBe($task->id);

    Notification::assertSentToTimes($staff, DsgvoRequested::class, 1);
});

it('completes the task when the studio answers the request', function () {
    useSurface(Surface::ADMIN);

    $contact = Contact::factory()->create();
    $task = Task::factory()->create(['case_id' => null, 'contact_id' => $contact->id]);
    DsgvoRequest::create(['contact_id' => $contact->id, 'kind' => DsgvoRequest::EXPORT, 'task_id' => $task->id]);

    test()->actingAs(staffMember(App\Enums\StaffRole::Admin), 'staff')
        ->get("/kontakte/{$contact->public_id}/dsgvo/export")
        ->assertOk();

    expect($task->fresh()->isDone())->toBeTrue();
});
