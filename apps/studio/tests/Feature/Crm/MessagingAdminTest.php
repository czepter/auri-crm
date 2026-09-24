<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Engagement\Actions\StartThread;
use App\Engagement\Models\Message;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows staff the thread a client started, with no compose action to originate one', function () {
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    app(StartThread::class)($project, $contact, 'Frage zum Ablauf', 'Dürfen wir nah an den Altar?');

    $user = staffMember();

    $props = $this->actingAs($user, 'staff')
        ->get("/auftraege/{$project->public_id}/nachrichten")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['thread']['subject'])->toBe('Frage zum Ablauf')
        ->and($props['thread']['messages'][0]['author'])->toBe('Lena Meier')
        ->and($props['thread']['messages'][0]['fromStaff'])->toBeFalse();
});

it('shows an empty state when no client has started a thread yet', function () {
    $project = ClientCase::factory()->create();

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/auftraege/{$project->public_id}/nachrichten")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['thread'])->toBeNull();
});

it('lets staff reply on an existing thread', function () {
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();
    app(StartThread::class)($project, $contact, 'Thema', 'Erste Nachricht.');
    $user = staffMember();

    $this->actingAs($user, 'staff')
        ->post("/auftraege/{$project->public_id}/nachrichten", ['body' => 'Antwort vom Studio.'])
        ->assertRedirect();

    $reply = Message::whereNull('contact_id')->sole();
    expect($reply->author_id)->toBe($user->id)
        ->and($reply->body)->toBe('Antwort vom Studio.');
});

it('refuses staff a reply when no thread exists yet, rather than starting one on the client\'s behalf', function () {
    $project = ClientCase::factory()->create();

    $this->actingAs(staffMember(), 'staff')
        ->post("/auftraege/{$project->public_id}/nachrichten", ['body' => 'Hallo?'])
        ->assertNotFound();

    expect(Message::count())->toBe(0);
});

it('lets a second shooter reply on the thread of a project they are crewed on', function () {
    $nina = User::factory()->withMfa()->create(['role' => StaffRole::Contributor]);
    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->for($project, 'case')->create();
    CrewAssignment::factory()->for($shoot)->for($nina)->create();
    $contact = Contact::factory()->create();
    app(StartThread::class)($project, $contact, 'Thema', 'Erste Nachricht.');

    $this->actingAs($nina, 'staff')
        ->post("/auftraege/{$project->public_id}/nachrichten", ['body' => 'Klar, gerne.'])
        ->assertRedirect();

    expect(Message::whereNull('contact_id')->count())->toBe(1);
});

it('refuses a second shooter the thread of a project they are not crewed on', function () {
    $nina = User::factory()->withMfa()->create(['role' => StaffRole::Contributor]);
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();
    $contact = Contact::factory()->create();
    app(StartThread::class)($project, $contact, 'Thema', 'Erste Nachricht.');

    $this->actingAs($nina, 'staff')
        ->get("/auftraege/{$project->public_id}/nachrichten")
        ->assertForbidden();

    $this->post("/auftraege/{$project->public_id}/nachrichten", ['body' => 'Klar, gerne.'])
        ->assertForbidden();
});
