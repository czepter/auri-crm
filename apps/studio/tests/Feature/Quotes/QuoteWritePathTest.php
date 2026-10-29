<?php

use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Quotes\Actions\AdoptQuoteRecipient;
use App\Quotes\Actions\CreateQuote;
use App\Quotes\Actions\DeleteQuote;
use App\Quotes\Actions\DuplicateQuote;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function quoteApi(string $key)
{
    return test()->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageQuotes)->create()))
        ->withHeader('Idempotency-Key', $key);
}

function expectCalls(string $action, int $times): void
{
    $dependencies = array_map(
        fn (ReflectionParameter $parameter) => app($parameter->getType()->getName()),
        (new ReflectionClass($action))->getConstructor()?->getParameters() ?? [],
    );

    test()->instance($action, Mockery::mock($action, $dependencies)->makePartial()->shouldReceive('__invoke')->times($times)->passthru()->getMock());
}

it('creates quotes and addenda through CreateQuote from admin and api', function () {
    expectCalls(CreateQuote::class, 4);
    [$user, $project] = studioWithProject();
    $accepted = Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Accepted]);

    test()->actingAs($user, 'staff');
    test()->post("/auftraege/{$project->public_id}/angebote")->assertRedirect();
    test()->post("/auftraege/{$project->public_id}/angebote", ['addendum_to' => $accepted->public_id])->assertRedirect();

    useSurface(Surface::API);
    quoteApi('create')->postJson("/api/cases/{$project->public_id}/quotes", [])->assertCreated();
    quoteApi('addendum')->postJson("/api/quotes/{$accepted->public_id}/addendum")->assertCreated();

    expect($project->quotes()->count())->toBe(5);
});

it('duplicates quotes through DuplicateQuote from admin and api', function () {
    expectCalls(DuplicateQuote::class, 2);
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    test()->actingAs($user, 'staff')->post("/angebote/{$quote->public_id}/duplizieren")->assertRedirect();

    useSurface(Surface::API);
    quoteApi('duplicate')->postJson("/api/quotes/{$quote->public_id}/duplicate")->assertCreated();

    expect($project->quotes()->count())->toBe(3);
});

it('deletes draft quotes through DeleteQuote from admin and api', function () {
    expectCalls(DeleteQuote::class, 4);
    [$user, $project] = studioWithProject();
    $adminDraft = Quote::factory()->for($project, 'case')->create();
    $apiDraft = Quote::factory()->for($project, 'case')->create();
    $sent = Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent]);

    test()->actingAs($user, 'staff');
    test()->delete("/angebote/{$adminDraft->public_id}")->assertRedirect();
    test()->delete("/angebote/{$sent->public_id}")->assertStatus(422);

    useSurface(Surface::API);
    quoteApi('delete')->deleteJson("/api/quotes/{$apiDraft->public_id}")->assertNoContent();
    quoteApi('delete-sent')->deleteJson("/api/quotes/{$sent->public_id}")->assertUnprocessable();

    expect($project->quotes()->pluck('id')->all())->toBe([$sent->id]);
});

it('adopts the recipient address through AdoptQuoteRecipient from admin and api', function () {
    expectCalls(AdoptQuoteRecipient::class, 2);
    [$user, $project] = studioWithProject();
    $contact = Contact::factory()->create();
    $quote = Quote::factory()->for($project, 'case')->create([
        'recipient_contact_id' => $contact->id, 'recipient_street' => 'Weg 1', 'recipient_postcode' => '07743', 'recipient_city' => 'Jena',
    ]);

    test()->actingAs($user, 'staff')->post("/angebote/{$quote->public_id}/empfaenger-uebernehmen")->assertRedirect();

    useSurface(Surface::API);
    quoteApi('adopt')->postJson("/api/quotes/{$quote->public_id}/adopt-recipient")->assertOk();

    expect($contact->fresh()->city)->toBe('Jena');
});
