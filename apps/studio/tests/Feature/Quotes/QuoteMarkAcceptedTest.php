<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->case = ClientCase::factory()->create();
    $this->case->attachContact($this->contact = Contact::factory()->create(), CaseContactRole::Primary);
});

it('nimmt ein versendetes Angebot für den Hauptkontakt an und bucht einmal', function () {
    $quote = Quote::factory()->for($this->case, 'case')->create(['status' => QuoteStatus::Sent]);
    $owner = User::factory()->withMfa()->create();

    $this->actingAs($owner, 'staff')->post("/angebote/{$quote->public_id}/angenommen")->assertRedirect();
    $this->actingAs($owner, 'staff')->post("/angebote/{$quote->public_id}/angenommen")->assertRedirect();

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Accepted)
        ->and($quote->accepted_ip)->toBeNull()
        ->and($quote->accepted_by_contact_id)->toBe($this->contact->id)
        ->and($this->case->fresh()->isBooked())->toBeTrue();
});

it('nimmt an, bucht aber nicht ohne Buchungsrecht', function () {
    $quote = Quote::factory()->for($this->case, 'case')->create(['status' => QuoteStatus::Sent]);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Manager]), 'staff')
        ->post("/angebote/{$quote->public_id}/angenommen")
        ->assertRedirect();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
        ->and($this->case->fresh()->isBooked())->toBeFalse();
});

it('lehnt einen Entwurf ab', function () {
    $quote = Quote::factory()->for($this->case, 'case')->create(['status' => QuoteStatus::Draft]);

    $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->post("/angebote/{$quote->public_id}/angenommen")
        ->assertSessionHasErrors('quote');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft)
        ->and($this->case->fresh()->isBooked())->toBeFalse();
});

it('verweigert ohne manageQuotes', function () {
    $quote = Quote::factory()->for($this->case, 'case')->create(['status' => QuoteStatus::Sent]);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Contributor]), 'staff')
        ->post("/angebote/{$quote->public_id}/angenommen")
        ->assertForbidden();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});
