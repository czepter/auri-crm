<?php

use App\Enums\Capability;
use App\Finance\Models\FinanceDocument;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const RECEIPT_PDF = "%PDF-1.4\n%receipt\n";

beforeEach(function () {
    useSurface(Surface::API);
    Storage::fake('s3');
    $this->travelTo('2026-11-22 10:00:00');
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageFinances)->create()));
});

function announceReceipt(array $overrides = [], string $key = 'up-1')
{
    return test()->withHeader('Idempotency-Key', $key)->postJson('/api/finance/documents/uploads', [
        'filename' => 'rechnung.pdf', 'mime' => 'application/pdf', 'size' => strlen(RECEIPT_PDF), 'source_ref' => ['channel' => 'mail'], ...$overrides,
    ]);
}

function putReceipt(string $url, string $body = RECEIPT_PDF)
{
    return test()->withoutHeader('Authorization')->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $body);
}

it('announces a receipt and takes its bytes at the signed address', function () {
    $response = announceReceipt()->assertCreated()->assertJsonPath('duplicate', false);
    $document = FinanceDocument::query()->sole();

    expect($response->json('documentId'))->toBe($document->public_id)
        ->and($response->json('uploadUrl'))->toStartWith(config('app.url').'/api/finance/documents/'.$document->public_id.'/content?')
        ->and($document->uploaded_at)->toBeNull()
        ->and($document->source_ref)->toBe(['channel' => 'mail']);

    putReceipt($response->json('uploadUrl'))->assertOk()->assertJsonPath('sha256', hash('sha256', RECEIPT_PDF));

    $document->refresh();
    expect($document->sha256)->toBe(hash('sha256', RECEIPT_PDF))
        ->and($document->size)->toBe(strlen(RECEIPT_PDF))
        ->and($document->uploaded_at)->not->toBeNull();
    Storage::disk('s3')->assertExists($document->storageKey());
});

it('replays an announcement to the same row', function () {
    $first = announceReceipt()->json();
    $second = announceReceipt()->json();

    expect($second)->toEqual($first)
        ->and(FinanceDocument::query()->count())->toBe(1);
});

it('hands back a receipt the studio already holds without an address', function () {
    $held = FinanceDocument::factory()->create(['sha256' => hash('sha256', RECEIPT_PDF)]);

    announceReceipt(['sha256' => hash('sha256', RECEIPT_PDF)])->assertOk()->assertJson(['documentId' => $held->public_id, 'uploadUrl' => null, 'duplicate' => true]);

    expect(FinanceDocument::query()->count())->toBe(1);
});

it('refuses an unsigned, a tampered and an expired address', function () {
    $url = announceReceipt()->json('uploadUrl');

    putReceipt(strtok($url, '?'))->assertForbidden();
    putReceipt(preg_replace('/expires=(\d+)/', 'expires=9999999999', $url))->assertForbidden();
    putReceipt(preg_replace('/signature=[0-9a-f]{4}/', 'signature=0000', $url))->assertForbidden();

    $this->travel(16)->minutes();
    putReceipt($url)->assertForbidden();

    expect(FinanceDocument::query()->sole()->uploaded_at)->toBeNull();
});

it('fills a row once and refuses a second put', function () {
    $url = announceReceipt()->json('uploadUrl');

    putReceipt($url)->assertOk();
    putReceipt($url, "%PDF-1.4\n%other\n")->assertStatus(409);
});

it('refuses a file another document already holds and names it', function () {
    $held = FinanceDocument::factory()->create(['sha256' => hash('sha256', RECEIPT_PDF)]);
    $url = announceReceipt()->json('uploadUrl');

    putReceipt($url)->assertStatus(409)->assertJsonPath('document_id', $held->public_id);

    expect(FinanceDocument::query()->whereNotNull('uploaded_at')->where('sha256', '!=', $held->sha256)->count())->toBe(0);
    Storage::disk('s3')->assertDirectoryEmpty('finance');
});

it('refuses an oversize body and a disguised executable', function () {
    $url = announceReceipt()->json('uploadUrl');

    putReceipt($url, "%PDF-1.4\n".str_repeat('a', 10 * 1024 * 1024))->assertStatus(413);
    putReceipt($url, "MZ\x90\x00 not a pdf")->assertStatus(422)->assertJsonValidationErrors('file');
    putReceipt($url, '')->assertStatus(422);

    expect(FinanceDocument::query()->sole()->uploaded_at)->toBeNull();
});

it('announces only a known type within the size limit', function () {
    announceReceipt(['mime' => 'application/x-msdownload'], 'bad-1')->assertUnprocessable();
    announceReceipt(['size' => 10 * 1024 * 1024 + 1], 'bad-2')->assertUnprocessable();
    announceReceipt(['sha256' => 'xyz'], 'bad-3')->assertUnprocessable();
});
