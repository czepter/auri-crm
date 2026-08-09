<?php

use App\Contracts\Actions\SignContract;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Events\ContractSigned;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// renderedContract() lives in tests/Pest.php — see the note there.

it('pins the exact stored bytes by hash and names the template version', function () {
    Storage::fake('local');

    $contract = renderedContract(null, '%PDF-exact-bytes');
    $contact = Contact::factory()->create();

    $signature = app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    expect($signature->document_sha256)->toBe(hash('sha256', '%PDF-exact-bytes'))
        ->and($signature->contract_template_version)->toBe($contract->template->version)
        ->and($signature->typed_name)->toBe('Lena Meier')
        ->and($signature->ip_address)->toBe('203.0.113.5')
        ->and($signature->user_agent)->toBe('Mozilla/5.0')
        ->and($signature->consented_at)->not->toBeNull()
        ->and($signature->contact_id)->toBe($contact->id)
        ->and($signature->contract_id)->toBe($contract->id);
});

it('re-hashing the stored artifact reproduces the recorded SHA-256', function () {
    Storage::fake('local');

    $contract = renderedContract(null, '%PDF-reproducible-bytes');
    $contact = Contact::factory()->create();

    $signature = app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    $rehash = hash('sha256', Storage::disk('local')->get($contract->artifact_path));

    expect($rehash)->toBe($signature->document_sha256);
});

it('leaves an existing signature untouched when the template is edited afterwards', function () {
    Storage::fake('local');

    $contract = renderedContract(null, '%PDF-bytes');
    $contact = Contact::factory()->create();
    $signature = app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    $contract->template->newVersion(['name' => $contract->template->name, 'body' => '<p>changed</p>']);

    expect($signature->fresh()->contract_template_version)->toBe(1)
        ->and($signature->fresh()->document_sha256)->toBe(hash('sha256', '%PDF-bytes'))
        ->and($contract->fresh()->contract_template_id)->toBe($contract->contract_template_id);
});

it('fires ContractSigned exactly once, carrying the contract and the signature', function () {
    Event::fake([ContractSigned::class]);
    Storage::fake('local');

    $contract = renderedContract();
    $contact = Contact::factory()->create();

    $signature = app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    Event::assertDispatchedTimes(ContractSigned::class, 1);
    Event::assertDispatched(ContractSigned::class, fn (ContractSigned $event) => $event->contract->is($contract) && $event->signature->is($signature));
});

it('is a no-op that writes nothing new when a contract is already signed', function () {
    Storage::fake('local');

    $contract = renderedContract();
    $contact = Contact::factory()->create();
    $first = app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    Event::fake([ContractSigned::class]);
    $second = app(SignContract::class)($contract, $contact, 'Someone Else', '198.51.100.1', 'Other/1.0');

    Event::assertNotDispatched(ContractSigned::class);
    expect($second->is($first))->toBeTrue()
        ->and(ContractSignature::count())->toBe(1)
        ->and($second->typed_name)->toBe('Lena Meier');
});

it('refuses to sign a contract with no rendered artifact', function () {
    $contract = Contract::factory()->create(['status' => ContractStatus::Pending]);
    $contact = Contact::factory()->create();

    app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('is append-only: a signature can never be updated or deleted', function () {
    Storage::fake('local');

    $contract = renderedContract();
    $contact = Contact::factory()->create();
    $signature = app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    expect(fn () => $signature->update(['typed_name' => 'Tampered']))
        ->toThrow(RuntimeException::class, 'Contract signatures are append-only.');

    expect(fn () => $signature->delete())
        ->toThrow(RuntimeException::class, 'Contract signatures are append-only.');
});
