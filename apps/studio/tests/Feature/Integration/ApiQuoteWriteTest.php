<?php

use App\Catalog\Models\Package;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
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
        ->and($quote->fresh()->sent_at->toDateString())->toBe('2018-11-20');
    Notification::assertNothingSent();
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
