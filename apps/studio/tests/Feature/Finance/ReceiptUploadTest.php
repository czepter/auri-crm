<?php

use App\Finance\Models\Expense;
use App\Finance\Models\FinanceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->admin = staffMember();
});

function pdfUpload(string $name = 'rechnung.pdf', string $body = "%PDF-1.4\n%receipt\n"): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $body);
}

it('stores a receipt under a generated key and names the sniffed type', function (string $name, string $body, string $mime) {
    $response = $this->actingAs($this->admin, 'staff')->postJson('/finanzen/belege', ['file' => UploadedFile::fake()->createWithContent($name, $body)])->assertCreated();

    $document = FinanceDocument::query()->sole();

    expect($response->json('document.id'))->toBe($document->public_id)
        ->and($response->json('document.url'))->toBe("/finanzen/belege/{$document->public_id}")
        ->and($document->mime)->toBe($mime)
        ->and($document->sha256)->toBe(hash('sha256', $body))
        ->and($document->storageKey())->toBe("finance/{$document->tenant_id}/{$document->public_id}");

    Storage::disk('s3')->assertExists($document->storageKey());
})->with([
    'pdf' => ['a.pdf', "%PDF-1.4\n%x\n", 'application/pdf'],
    'png named txt' => ['b.txt', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 'image/png'],
    'jpeg' => ['c.jpg', syntheticJpeg(), 'image/jpeg'],
]);

it('refuses a disguised executable, an oversize file and an empty file', function () {
    $this->actingAs($this->admin, 'staff');

    $this->postJson('/finanzen/belege', ['file' => pdfUpload('x.pdf', "MZ\x90\x00 not a pdf")])->assertJsonValidationErrors(['file' => __('finance.unsupportedFile')]);
    $this->postJson('/finanzen/belege', ['file' => pdfUpload('x.pdf', '')])->assertJsonValidationErrors('file');
    $this->postJson('/finanzen/belege', ['file' => pdfUpload('big.pdf', "%PDF-1.4\n".str_repeat('a', 10 * 1024 * 1024))])->assertJsonValidationErrors(['file' => __('finance.tooLarge')]);

    expect(FinanceDocument::query()->count())->toBe(0);
});

it('returns the first document for the same bytes and names its expense', function () {
    $this->actingAs($this->admin, 'staff');

    $first = $this->postJson('/finanzen/belege', ['file' => pdfUpload()])->assertCreated();
    $expense = Expense::factory()->create();
    $expense->documents()->attach(FinanceDocument::query()->sole());

    $second = $this->postJson('/finanzen/belege', ['file' => pdfUpload('andere-bezeichnung.pdf')])->assertOk();

    expect($second->json('document.id'))->toBe($first->json('document.id'))
        ->and($second->json('duplicateOfExpenseId'))->toBe($expense->public_id)
        ->and(FinanceDocument::query()->count())->toBe(1);
});

it('streams a receipt inline under a sandbox and nosniff', function () {
    $this->actingAs($this->admin, 'staff');
    $id = $this->postJson('/finanzen/belege', ['file' => pdfUpload()])->json('document.id');

    $response = $this->get("/finanzen/belege/$id")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toBe('sandbox')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

it('answers 404 for an unknown id and for another tenant’s document', function () {
    $this->actingAs($this->admin, 'staff');
    $foreign = FinanceDocument::factory()->create();
    FinanceDocument::query()->whereKey($foreign->id)->toBase()->update(['tenant_id' => '00000000-0000-4000-8000-00000000dead']);

    $this->get('/finanzen/belege/'.$foreign->public_id)->assertNotFound();
    $this->get('/finanzen/belege/aaaaaaaaaaaaaaaaaaaaaaaa')->assertNotFound();
});
