<?php

use App\Catalog\Models\Package;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageQuotes)->create()));
});

it('creates a draft quote and edits one field without losing the rest', function () {
    $project = Project::factory()->create();

    $id = $this->withHeader('Idempotency-Key', 'quote-1')
        ->postJson("/api/projects/{$project->public_id}/quotes", [
            'intro' => 'Hallo ihr zwei',
            'items' => [['title' => 'Reportage 2', 'quantity' => 1, 'unit_price' => 179_000]],
            'stages' => [['percent' => 30, 'label' => 'Anzahlung']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', QuoteStatus::Draft->value)
        ->assertJsonPath('data.total', 179_000)
        ->json('data.id');

    $this->withHeader('Idempotency-Key', 'quote-2')
        ->patchJson("/api/quotes/{$id}", ['intro' => 'Liebe Viktoria'])
        ->assertOk()
        ->assertJsonPath('data.intro', 'Liebe Viktoria')
        ->assertJsonPath('data.items.0.title', 'Reportage 2')
        ->assertJsonPath('data.paymentStages.0.label', 'Anzahlung');
});

// #396: the API store created a quote with no number and no recipient,
// unlike the admin store's (Quotes\Http\Controllers\QuoteController::store).
it('gives a new quote a number and the project\'s payer as recipient, like the admin store does', function () {
    $project = Project::factory()->create();
    $payer = Contact::factory()->create();
    $project->attachContact($payer, ProjectContactRole::Payer);

    $this->withHeader('Idempotency-Key', 'quote-numbered')
        ->postJson("/api/projects/{$project->public_id}/quotes", [])
        ->assertCreated()
        ->assertJsonPath('data.recipientContact', $payer->public_id)
        ->tap(fn ($response) => expect($response->json('data.number'))->not->toBeNull());
});

it('switches the slider on when a rate arrives without a mode', function () {
    $project = Project::factory()->create();

    $id = $this->withHeader('Idempotency-Key', 'rate-no-mode')
        ->postJson("/api/projects/{$project->public_id}/quotes", ['hourly_rate' => 25_000, 'min_hours' => 4, 'max_hours' => 10, 'hours' => 6])
        ->assertCreated()
        ->assertJsonPath('data.pricingMode', 'choice')
        ->json('data.id');

    $this->withHeader('Idempotency-Key', 'rate-cleared')
        ->patchJson("/api/quotes/{$id}", ['hourly_rate' => null, 'min_hours' => null, 'max_hours' => null, 'hours' => null])
        ->assertOk()
        ->assertJsonPath('data.pricingMode', 'classic');
});

it('starts a quote from a listed package, keeping its lines under a new intro', function () {
    $package = Package::factory()->create(['name' => 'Reportage 2']);
    $package->items()->create(['title' => 'Reportage 10 Stunden', 'quantity' => 1, 'unit_price' => 179_000]);
    $project = Project::factory()->create();

    $id = $this->getJson('/api/packages')->assertOk()->json('data.0.id');

    $this->withHeader('Idempotency-Key', 'from-package')
        ->postJson("/api/projects/{$project->public_id}/quotes", ['package' => $id, 'intro' => 'Hallo'])
        ->assertCreated()
        ->assertJsonPath('data.items.0.title', 'Reportage 10 Stunden')
        ->assertJsonPath('data.total', 179_000)
        ->assertJsonPath('data.intro', 'Hallo');
});

// The case the tool exists for: a booking that happened by mail, entered
// after the fact, without mailing the client a quote they signed long ago.
it('records a past quote as sent and accepted, silently', function () {
    Notification::fake();
    $project = Project::factory()->create();
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
    $quote = Quote::factory()->for($project)->create(['valid_until' => '2018-12-31']);

    $this->withHeaders(['Idempotency-Key' => 'send-1', 'X-Silent-Write' => '1'])
        ->postJson("/api/quotes/{$quote->public_id}/send", ['sent_at' => '2018-11-20'])
        ->assertOk();

    $this->withHeaders(['Idempotency-Key' => 'accept-1', 'X-Silent-Write' => '1'])
        ->postJson("/api/quotes/{$quote->public_id}/accept", ['accepted_at' => '2018-12-03'])
        ->assertOk()
        ->assertJsonPath('data.status', QuoteStatus::Accepted->value);

    expect($quote->fresh()->accepted_at->toDateString())->toBe('2018-12-03')
        ->and($quote->fresh()->sent_at->toDateString())->toBe('2018-11-20')
        // #396: a silent send never left the app, so it is channel self —
        // MarkQuoteSent's own value, not SendQuote's 'mail'.
        ->and($quote->fresh()->sent_channel)->toBe('self');
    Notification::assertNothingSent();
});

// #396: AcceptQuote::configuration() deselects every switchable line the
// caller does not name, and the API passed it none at all.
it('keeps the add-ons the client switched on when accepting an hourly quote', function () {
    $project = Project::factory()->create();
    $project->attachContact($contact = Contact::factory()->create(), ProjectContactRole::Primary);
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'pricing_mode' => 'choice',
        'hourly_rate' => 10000,
        'min_hours' => 1,
        'max_hours' => 10,
    ]);
    $addon = QuoteItem::factory()->for($quote)->create(['selectable' => true, 'selected' => false, 'unit_price' => 20000, 'quantity' => 1]);

    $this->withHeader('Idempotency-Key', 'accept-addon')
        ->postJson("/api/quotes/{$quote->public_id}/accept", ['hours' => 3, 'items' => [$addon->public_id]])
        ->assertOk();

    expect($addon->fresh()->selected)->toBeTrue();
});

it('refuses a re-accept that carries different hours than the one already recorded', function () {
    $project = Project::factory()->create();
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now(),
        'accepted_by_contact_id' => $project->primaryContact()->id,
        'hours' => 4,
    ]);

    $this->withHeader('Idempotency-Key', 're-accept')
        ->postJson("/api/quotes/{$quote->public_id}/accept", ['hours' => 9])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quote');

    expect($quote->fresh()->hours)->toBe(4);
});

it('refuses to edit a sent quote', function () {
    $quote = Quote::factory()->create(['status' => QuoteStatus::Sent]);

    $this->withHeader('Idempotency-Key', 'edit-sent')
        ->patchJson("/api/quotes/{$quote->public_id}", ['intro' => 'Neu'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quote');
});

it('refuses a quote write to a token without ManageQuotes', function () {
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageLeads)->create()))
        ->withHeader('Idempotency-Key', 'quote-forbidden')
        ->postJson("/api/projects/{$project->public_id}/quotes", [])
        ->assertForbidden();
});
