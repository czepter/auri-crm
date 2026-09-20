<?php

use App\Catalog\Models\Package;
use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageQuotes)->create()));
});

it('adds an addendum to an accepted quote, leaving it accepted', function () {
    $quote = Quote::factory()->create(['status' => QuoteStatus::Accepted]);

    $id = $this->withHeader('Idempotency-Key', 'addendum-1')
        ->postJson("/api/quotes/{$quote->public_id}/addendum")
        ->assertCreated()
        ->assertJsonPath('data.status', QuoteStatus::Draft->value)
        ->json('data.id');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
        ->and(Quote::query()->where('public_id', $id)->sole()->predecessor_id)->toBe($quote->id);
});

it('refuses an addendum on a quote that is not accepted', function () {
    $quote = Quote::factory()->create(['status' => QuoteStatus::Draft]);

    $this->withHeader('Idempotency-Key', 'addendum-2')
        ->postJson("/api/quotes/{$quote->public_id}/addendum")
        ->assertUnprocessable();
});

it('duplicates a quote into a new independent draft', function () {
    $quote = Quote::factory()->create(['status' => QuoteStatus::Sent]);
    $quote->items()->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 100_000]);

    $id = $this->withHeader('Idempotency-Key', 'dup-1')
        ->postJson("/api/quotes/{$quote->public_id}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.status', QuoteStatus::Draft->value)
        ->assertJsonPath('data.items.0.title', 'Reportage')
        ->json('data.id');

    expect($id)->not->toBe($quote->public_id);
});

it('deletes a draft quote but refuses a sent one', function () {
    $draft = Quote::factory()->create(['status' => QuoteStatus::Draft]);
    $sent = Quote::factory()->create(['status' => QuoteStatus::Sent]);

    $this->withHeader('Idempotency-Key', 'del-1')
        ->deleteJson("/api/quotes/{$draft->public_id}")
        ->assertNoContent();

    expect(Quote::query()->where('public_id', $draft->public_id)->exists())->toBeFalse();

    $this->withHeader('Idempotency-Key', 'del-2')
        ->deleteJson("/api/quotes/{$sent->public_id}")
        ->assertUnprocessable();
});

it('marks a quote sent by the studio itself, silently', function () {
    $quote = Quote::factory()->create(['status' => QuoteStatus::Draft, 'valid_until' => '2030-01-01']);

    $this->withHeaders(['Idempotency-Key' => 'sent-1', 'X-Silent-Write' => '1'])
        ->postJson("/api/quotes/{$quote->public_id}/mark-sent")
        ->assertOk()
        ->assertJsonPath('data.status', QuoteStatus::Sent->value);

    expect($quote->fresh()->sent_channel)->toBe('self');
});

it('writes a quote’s recipient address back onto its contact', function () {
    $contact = Contact::factory()->create(['street' => 'Alte Straße 1']);
    $quote = Quote::factory()->create([
        'recipient_contact_id' => $contact->id,
        'recipient_street' => 'Neue Straße 5',
        'recipient_postcode' => '12345',
        'recipient_city' => 'Berlin',
    ]);

    $this->withHeader('Idempotency-Key', 'adopt-1')
        ->postJson("/api/quotes/{$quote->public_id}/adopt-recipient")
        ->assertOk();

    expect($contact->fresh()->street)->toBe('Neue Straße 5');
});

it('saves a whole quote as a package, carrying the hourly rate', function () {
    $quote = Quote::factory()->create(['hourly_rate' => 20_000, 'min_hours' => 2, 'max_hours' => 5]);
    $quote->items()->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 100_000]);

    $this->withHeader('Idempotency-Key', 'pkg-1')
        ->postJson("/api/quotes/{$quote->public_id}/save-as-package", ['name' => 'Neues Paket'])
        ->assertCreated();

    $package = Package::query()->where('name', 'Neues Paket')->sole();
    expect($package->hourly_rate)->toBe(20_000)
        ->and($package->items()->count())->toBe(1);
});

it('saves a single quote line as a package', function () {
    $quote = Quote::factory()->create();
    $item = $quote->items()->create(['title' => 'Zusatzalbum', 'quantity' => 1, 'unit_price' => 30_000]);
    $quote->items()->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 100_000]);

    $this->withHeader('Idempotency-Key', 'pkg-2')
        ->postJson("/api/quotes/{$quote->public_id}/save-as-package", ['name' => 'Nur Album', 'item' => $item->public_id])
        ->assertCreated();

    $package = Package::query()->where('name', 'Nur Album')->sole();
    expect($package->items()->count())->toBe(1)
        ->and($package->items()->sole()->title)->toBe('Zusatzalbum');
});
