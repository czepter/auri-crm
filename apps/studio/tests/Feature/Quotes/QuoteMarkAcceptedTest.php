<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->project = Project::factory()->create();
    $this->project->attachContact($this->contact = Contact::factory()->create(), ProjectContactRole::Primary);
});

it('nimmt ein versendetes Angebot für den Hauptkontakt an und bucht einmal', function () {
    $quote = Quote::factory()->for($this->project)->create(['status' => QuoteStatus::Sent]);
    $owner = User::factory()->withMfa()->create();

    $this->actingAs($owner, 'staff')->post("/angebote/{$quote->public_id}/angenommen")->assertRedirect();
    $this->actingAs($owner, 'staff')->post("/angebote/{$quote->public_id}/angenommen")->assertRedirect();

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Accepted)
        ->and($quote->accepted_ip)->toBeNull()
        ->and($quote->accepted_by_contact_id)->toBe($this->contact->id)
        ->and($this->project->job()->count())->toBe(1);
});

it('nimmt an, bucht aber nicht ohne Buchungsrecht', function () {
    $quote = Quote::factory()->for($this->project)->create(['status' => QuoteStatus::Sent]);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Editor]), 'staff')
        ->post("/angebote/{$quote->public_id}/angenommen")
        ->assertRedirect();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
        ->and($this->project->job()->exists())->toBeFalse();
});

it('lehnt einen Entwurf ab', function () {
    $quote = Quote::factory()->for($this->project)->create(['status' => QuoteStatus::Draft]);

    $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->post("/angebote/{$quote->public_id}/angenommen")
        ->assertSessionHasErrors('quote');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft)
        ->and($this->project->job()->exists())->toBeFalse();
});

it('verweigert ohne manageQuotes', function () {
    $quote = Quote::factory()->for($this->project)->create(['status' => QuoteStatus::Sent]);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::SecondShooter]), 'staff')
        ->post("/angebote/{$quote->public_id}/angenommen")
        ->assertForbidden();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});
