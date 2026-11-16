<?php

use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Integration\Models\ApiToken;
use App\Payments\Models\CreditNote;
use App\Payments\Models\CreditNoteDraft;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceDraft;
use App\Settings\Actions\UpdateStudioSetting;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    Bus::fake();
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageContracts)->create()));
});

function invoiceBody(Contact $contact, array $override = []): array
{
    return [
        'contact_id' => $contact->public_id,
        'due_on' => '2026-12-01',
        'intro' => 'Hallo',
        'items' => [['title' => 'Abzüge', 'quantity' => 2, 'unit_price' => 1_000]],
        ...$override,
    ];
}

it('issues an invoice from positions and lists and shows it', function () {
    $contact = Contact::factory()->create();

    $id = $this->withHeader('Idempotency-Key', 'inv-1')
        ->postJson('/api/invoices', invoiceBody($contact))
        ->assertCreated()
        ->assertJsonPath('data.row.number', fn ($number) => str_starts_with($number, 'RE-'))
        ->assertJsonPath('data.row.amount', 2_380)
        ->assertJsonPath('data.items.0.title', 'Abzüge')
        ->json('data.row.id');

    $this->getJson('/api/invoices')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/invoices/{$id}")->assertOk()->assertJsonPath('data.recipient', $contact->name);
});

it('refuses an invoice without positions', function () {
    $this->withHeader('Idempotency-Key', 'inv-2')
        ->postJson('/api/invoices', invoiceBody(Contact::factory()->create(), ['items' => []]))
        ->assertUnprocessable();

    expect(Invoice::count())->toBe(0);
});

it('refuses a staff token whose role lacks the capability', function () {
    $user = staffMember(StaffRole::Contributor);
    $this->withHeader('Authorization', 'Bearer '.$user->createToken('t', [Capability::ManageContracts->value])->plainTextToken)
        ->getJson('/api/invoices')->assertForbidden();
});

it('refuses a token without the contracts scope', function () {
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageQuotes)->create()))
        ->getJson('/api/invoice-drafts')->assertForbidden();
});

it('keeps an invoice draft through create, update, list and issue', function () {
    $contact = Contact::factory()->create();

    $id = $this->withHeader('Idempotency-Key', 'd-1')
        ->postJson('/api/invoice-drafts', ['contact_id' => $contact->public_id, 'items' => [['title' => '']]])
        ->assertCreated()
        ->json('data.id');

    $this->withHeader('Idempotency-Key', 'd-2')
        ->putJson("/api/invoice-drafts/{$id}", invoiceBody($contact))
        ->assertOk()
        ->assertJsonPath('data.payload.items.0.title', 'Abzüge');

    $this->getJson('/api/invoice-drafts')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/invoice-drafts/{$id}")->assertOk()->assertJsonPath('data.contactId', $contact->public_id);

    $this->withHeader('Idempotency-Key', 'd-3')
        ->postJson("/api/invoice-drafts/{$id}/issue")
        ->assertCreated()
        ->assertJsonPath('data.row.amount', 2_380);

    expect(InvoiceDraft::count())->toBe(0)->and(Invoice::count())->toBe(1);
});

it('deletes an invoice draft and refuses issuing an unfinished one', function () {
    $draft = InvoiceDraft::create(['payload' => ['items' => [['title' => '']]]]);

    $this->withHeader('Idempotency-Key', 'd-4')
        ->postJson("/api/invoice-drafts/{$draft->public_id}/issue")
        ->assertUnprocessable();

    $this->withHeader('Idempotency-Key', 'd-5')
        ->deleteJson("/api/invoice-drafts/{$draft->public_id}")
        ->assertNoContent();

    expect(InvoiceDraft::count())->toBe(0)->and(Invoice::count())->toBe(0);
});

it('issues a credit note against a paid invoice and lists it', function () {
    $invoice = creditableInstallment()->invoice;
    CreditNoteDraft::create(['invoice_id' => $invoice->id, 'payload' => ['reason' => 'x']]);

    $this->withHeader('Idempotency-Key', 'cn-1')
        ->postJson("/api/invoices/{$invoice->public_id}/credit-notes", ['amount' => 5_000, 'reason' => 'Nachlass', 'outro' => 'Danke'])
        ->assertCreated()
        ->assertJsonPath('data.amount', 5_000)
        ->assertJsonPath('data.invoiceId', $invoice->public_id);

    expect(CreditNote::sole()->outro)->toBe('Danke')->and(CreditNoteDraft::count())->toBe(0);

    $this->getJson('/api/credit-notes')->assertOk()->assertJsonCount(1, 'data');
});

it('refuses a credit note dated before its invoice or larger than what was paid', function () {
    $invoice = creditableInstallment()->invoice;
    $invoice->update(['issued_at' => now()]);

    $this->withHeader('Idempotency-Key', 'cn-2')
        ->postJson("/api/invoices/{$invoice->public_id}/credit-notes", ['amount' => 5_000, 'reason' => 'Nachlass', 'issued_on' => now()->subDays(3)->toDateString()])
        ->assertUnprocessable()->assertJsonValidationErrors('issued_on');

    $this->withHeader('Idempotency-Key', 'cn-3')
        ->postJson("/api/invoices/{$invoice->public_id}/credit-notes", ['amount' => 99_999_999, 'reason' => 'Nachlass'])
        ->assertUnprocessable()->assertJsonValidationErrors('amount');

    expect(CreditNote::count())->toBe(0);
});

it('keeps one credit note draft per invoice', function () {
    $invoice = creditableInstallment()->invoice;
    $url = "/api/invoices/{$invoice->public_id}/credit-note-draft";

    $this->getJson($url)->assertOk()->assertJsonPath('data', null);

    $this->withHeader('Idempotency-Key', 'cd-1')->putJson($url, ['reason' => 'Erst', 'taken' => [true]])->assertOk();
    $this->withHeader('Idempotency-Key', 'cd-2')->putJson($url, ['reason' => 'Dann', 'taken' => [false]])->assertOk();

    expect(CreditNoteDraft::count())->toBe(1);
    $this->getJson($url)->assertJsonPath('data.reason', 'Dann');

    $this->withHeader('Idempotency-Key', 'cd-3')->deleteJson($url)->assertNoContent();
    expect(CreditNoteDraft::count())->toBe(0);
});
