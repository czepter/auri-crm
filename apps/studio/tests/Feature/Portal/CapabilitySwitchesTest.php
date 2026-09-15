<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
});

/**
 * T-08's own rule: a switched-off capability refuses the route it guards
 * exactly as a route that does not exist would — never merely a page with
 * the button removed. Every switch here defaults to off (the migration's own
 * column defaults), proven once per switch rather than assumed.
 */
it('defaults every client capability switch to off', function () {
    $row = StudioSettings::query()->sole();

    expect($row->portal_accept_quotes_enabled)->toBeFalse()
        ->and($row->portal_payment_status_enabled)->toBeFalse()
        ->and($row->portal_messaging_mode)->toBe('off');
});

it('404s the payment-status route the same way a route that does not exist would, while the switch is off', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    PaymentPlan::factory()->for($job)->create(['total' => 100000]);

    $missing = $this->actingAs($contact, 'portal')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/zahlungen");

    $response->assertNotFound();
    expect($response->status())->toBe($missing->status());
});

it('hides the payments link on the project overview while the switch is off, even though a payment plan exists', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    PaymentPlan::factory()->for($job)->create(['total' => 100000]);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['project']['documents'])->pluck('kind')->all())->not->toContain('payments');
});

it('shows the payments route and link once the studio switches payment-status on', function () {
    StudioSettings::query()->update(['portal_payment_status_enabled' => true]);

    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    PaymentPlan::factory()->for($job)->create(['total' => 100000]);

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/zahlungen")->assertOk();
});

it('404s the payments checkout route the same way a route that does not exist would, while the switch is off', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 100000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 100000]);

    $missing = $this->actingAs($contact, 'portal')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen");

    $response->assertNotFound();
    expect($response->status())->toBe($missing->status());
});

it('404s the accept-quote route the same way a route that does not exist would, while the switch is off', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'sent_at' => now(),
        'valid_until' => now()->addDays(14),
    ]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $missing = $this->actingAs($contact, 'portal')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs($contact, 'portal')->post("/projekte/{$project->public_id}/angebot/annehmen");

    $response->assertNotFound();
    expect($response->status())->toBe($missing->status());
    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('hides the accept-quote affordance on the quote screen while the switch is off, even though the quote is otherwise acceptable', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'sent_at' => now(),
        'valid_until' => now()->addDays(14),
    ]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['canAccept'])->toBeFalse();
});

it('404s the messages route the same way a route that does not exist would, while the switch is off', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $missing = $this->actingAs($contact, 'portal')->get('/this-route-does-not-exist-xyz');

    $get = $this->actingAs($contact, 'portal')->get('/nachrichten');
    $post = $this->post('/nachrichten', ['subject' => 'Thema', 'body' => 'Hallo?']);

    $get->assertNotFound();
    $post->assertNotFound();
    expect($get->status())->toBe($missing->status());
    expect(App\Engagement\Models\Thread::count())->toBe(0);
});

it('hides the messages link on the project overview while the switch is off', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['studio']['messagingEnabled'])->toBeFalse();
});

it('lets a client start a thread once the studio switches messaging on', function () {
    StudioSettings::query()->update(['portal_messaging_mode' => 'any']);

    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post('/nachrichten', ['subject' => 'Thema', 'body' => 'Hallo!', 'project' => $project->public_id])
        ->assertRedirect();

    expect(App\Engagement\Models\Thread::where('project_id', $project->id)->count())->toBe(1);
});

it('accepts once the studio switches accept-quotes on', function () {
    StudioSettings::query()->update(['portal_accept_quotes_enabled' => true]);

    $project = Project::factory()->create(['portal_visible' => true]);
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'sent_at' => now(),
        'valid_until' => now()->addDays(14),
    ]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertRedirect();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
});
