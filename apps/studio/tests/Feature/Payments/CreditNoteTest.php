<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\MergeFieldVocabulary;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Models\NumberCounter;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\CreditNote;
use App\Payments\Notifications\CreditNoteSent;
use App\Settings\Actions\UpdateStudioSetting;
use App\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Stripe\ApiRequestor;
use Stripe\Exception\CardException;
use Tests\Support\FakeStripeHttpClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
});

afterEach(fn () => ApiRequestor::setHttpClient(null));

it('allocates the number from the credit-note range, not the invoice one', function () {
    $installment = creditableInstallment();

    $note = app(IssueCreditNote::class)($installment->invoice, $installment->amount, 'Zweite Kamera nicht erbracht');

    expect($note->number)->toStartWith('GS-')
        ->and(NumberCounter::where('document_type', DocumentType::CreditNote->value)->count())->toBe(1)
        ->and(NumberCounter::where('document_type', DocumentType::Invoice->value)->count())->toBe(1);
});

it('credits the whole amount', function () {
    $installment = creditableInstallment();

    $note = app(IssueCreditNote::class)($installment->invoice, $installment->amount, 'Auftrag entfallen');

    expect($note->amount)->toBe($installment->amount)
        ->and($note->reason)->toBe('Auftrag entfallen')
        ->and($note->stripe_refund_id)->toBeNull();
});

it('credits a part, and caps the next one at what is left', function () {
    $installment = creditableInstallment();
    $issue = app(IssueCreditNote::class);

    $issue($installment->invoice, 20_000, 'Teilleistung entfallen');
    $issue($installment->fresh()->invoice, 10_000, 'Nachlass');

    expect($installment->fresh()->invoice->creditNotes)->toHaveCount(2);

    $remaining = $installment->amount - 30_000;

    expect(fn () => $issue($installment->fresh()->invoice, $remaining + 1, 'Zu viel'))
        ->toThrow(ValidationException::class);
});

it('refuses more than was paid', function () {
    $installment = creditableInstallment();

    expect(fn () => app(IssueCreditNote::class)($installment->invoice, $installment->amount + 1, 'Zu viel'))
        ->toThrow(ValidationException::class);

    expect(CreditNote::count())->toBe(0)
        ->and(NumberCounter::where('document_type', DocumentType::CreditNote->value)->count())->toBe(0);
});

it('refuses an invoice that was never paid', function () {
    Storage::fake('local');

    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    expect(fn () => app(IssueCreditNote::class)($installment->fresh()->invoice, 10_000, 'Zu früh'))
        ->toThrow(ValidationException::class);
});

it('refunds through Stripe and stores the refund id', function () {
    Cache::store('redis')->flush();
    StudioSettings::query()->first()->update([
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_secret_key' => 'sk_test_abc123',
    ]);

    $installment = creditableInstallment(SettledVia::Stripe, 'pi_test_456');

    $fake = new FakeStripeHttpClient([[
        'status' => 200,
        'body' => ['id' => 're_test_789', 'object' => 'refund', 'amount' => 50_000, 'status' => 'succeeded'],
    ]]);
    ApiRequestor::setHttpClient($fake);

    $note = app(IssueCreditNote::class)($installment->invoice, 50_000, 'Teilleistung entfallen');

    expect($note->fresh()->stripe_refund_id)->toBe('re_test_789')
        ->and($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]['params'])->toBe(['payment_intent' => 'pi_test_456', 'amount' => 50_000])
        ->and($fake->requests[0]['headers'])->toContain('Idempotency-Key: '.$note->public_id);
});

it('writes no credit note when the Stripe refund fails', function () {
    Cache::store('redis')->flush();
    StudioSettings::query()->first()->update([
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_secret_key' => 'sk_test_abc123',
    ]);

    $installment = creditableInstallment(SettledVia::Stripe, 'pi_test_456');

    ApiRequestor::setHttpClient(new FakeStripeHttpClient([[
        'status' => 402,
        'body' => ['error' => ['type' => 'card_error', 'message' => 'Charge has already been refunded.']],
    ]]));

    expect(fn () => app(IssueCreditNote::class)($installment->invoice, 50_000, 'Teilleistung entfallen'))
        ->toThrow(CardException::class);

    expect(CreditNote::count())->toBe(0)
        ->and(NumberCounter::where('document_type', DocumentType::CreditNote->value)->count())->toBe(0);
});

it('records the number, the invoice it credits and the reason', function () {
    $installment = creditableInstallment();

    app(IssueCreditNote::class)($installment->invoice, 25_000, 'Nachlass', staffMember(StaffRole::Admin));

    $log = AuditLog::where('event', 'payments.credit_note.issued')->sole();

    expect($log->context['amount'])->toBe(25_000)
        ->and($log->context['reason'])->toBe('Nachlass')
        ->and($log->context['invoice_number'])->toBe($installment->invoice->number)
        ->and($log->context['number'])->toStartWith('GS-');
});

it('renders the Gutschrift against the pinned template, with the reference line resolved', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::CreditNote,
        'body' => 'Gutschrift {{ creditNote.number }} über {{ creditNote.gross }}. '
            .'Zu Rechnung {{ creditNote.invoiceNumber }} vom {{ creditNote.invoiceDate }}. {{ creditNote.reason }}',
    ]);

    $installment = creditableInstallment();

    $this->mock(GotenbergClient::class)
        ->shouldReceive('htmlToPdf')
        ->once()
        ->andReturnUsing(function (string $html) use ($installment) {
            expect($html)->toContain('Gutschrift GS-')
                ->and($html)->toContain('Zu Rechnung '.$installment->invoice->number)
                ->and($html)->toContain('Nachlass')
                ->and($html)->not->toContain('{{');

            return '%PDF-1.4 fake';
        });

    $note = app(IssueCreditNote::class)($installment->invoice, 25_000, 'Nachlass');

    expect($note->fresh()->pdf_path)->toBe("credit-notes/{$note->tenant_id}/{$note->public_id}.pdf");
    Storage::disk('local')->assertExists($note->fresh()->pdf_path);
});

it('escapes merge-field values in the Gutschrift', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::CreditNote,
        'body' => '<p>{{ creditNote.reason }}</p>',
    ]);

    $installment = creditableInstallment();

    $this->mock(GotenbergClient::class)
        ->shouldReceive('htmlToPdf')
        ->once()
        ->andReturnUsing(function (string $html) {
            expect($html)->toContain('&lt;b&gt;x&lt;/b&gt;')->and($html)->not->toContain('<b>x</b>');

            return '%PDF-1.4 fake';
        });

    app(IssueCreditNote::class)($installment->invoice, 25_000, '<b>x</b>');
});

it('documents every creditNote path with a sample that resolves', function () {
    $paths = collect(MergeFieldVocabulary::paths())->filter(fn (string $path) => str_starts_with($path, 'creditNote.'));

    expect($paths->values()->all())->toContain(
        'creditNote.number', 'creditNote.date', 'creditNote.gross',
        'creditNote.invoiceNumber', 'creditNote.invoiceDate', 'creditNote.reason',
    );
});

it('lets staff issue a Gutschrift through the route and download it', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::CreditNote,
        'body' => 'Gutschrift {{ creditNote.number }}',
    ]);
    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->andReturn('%PDF-1.4 fake');

    $installment = creditableInstallment();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/rechnungen/{$installment->invoice->public_id}/gutschrift", ['amount' => 25_000, 'reason' => 'Nachlass'])
        ->assertRedirect();

    $note = CreditNote::sole();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/rechnungen/{$installment->invoice->public_id}/gutschrift/{$note->public_id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('sends the Gutschrift to the client alongside the invoice', function () {
    $installment = creditableInstallment();
    app(IssueCreditNote::class)($installment->invoice, 25_000, 'Nachlass');

    $data = PaymentPlanData::fromModel(
        $installment->plan->fresh()->load('installments.invoice.creditNotes'),
    );

    expect($data->installments[0]->creditNotes)->toHaveCount(1)
        ->and($data->installments[0]->creditNotes[0]['number'])->toStartWith('GS-')
        ->and($data->installments[0]->creditNotes[0]['amount'])->toBe(25_000)
        ->and($data->installments[1]->creditNotes)->toBe([]);
});

// #480: the plan loads its Gutschriften through the invoice now.
it('renders the project quote tab once a Gutschrift exists', function () {
    $installment = creditableInstallment();
    app(IssueCreditNote::class)($installment->invoice, 25_000, 'Nachlass');

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/auftraege/{$installment->plan->case->public_id}/angebote")
        ->assertOk();
});

it('opens the Gutschrift builder on what was paid, and not once all of it is credited', function () {
    $paid = creditableInstallment();
    $viewer = $this->actingAs(staffMember(), 'staff');

    $page = $viewer->get("/rechnungen/{$paid->invoice->public_id}/gutschrift/neu")->assertOk()->viewData('page');

    expect($page['component'])->toBe('Invoices/CreditNote')
        ->and($page['props']['creditable'])->toBe((int) $paid->amount)
        ->and($page['props']['invoice']['items'][0]['gross'])->toBe((int) $paid->amount);

    app(IssueCreditNote::class)($paid->invoice, (int) $paid->amount, 'Rückgabe', null);

    $viewer->get("/rechnungen/{$paid->invoice->public_id}/gutschrift/neu")->assertNotFound();
});

it('sends the Gutschrift in the studio’s own wording where the builder asks for it', function () {
    Notification::fake();
    mailDefault('credit_note_sent', 'Gutschrift {{ creditNote.number }}', 'Hallo {{ contact.name }}, {{ creditNote.gross }} gehen zurück.');

    $paid = creditableInstallment();
    $paid->invoice->contact->update(['email' => 'lena@example.test']);

    $this->actingAs(staffMember(), 'staff')
        ->post("/rechnungen/{$paid->invoice->public_id}/gutschrift", ['amount' => 10_000, 'reason' => 'Rückgabe', 'send' => true])
        ->assertRedirect();

    Notification::assertSentTo($paid->invoice->contact, CreditNoteSent::class, function (CreditNoteSent $mail) use ($paid) {
        $message = $mail->toMail($paid->invoice->contact);

        return str_starts_with($message->subject, 'Gutschrift GS-') && str_contains(implode(' ', $message->introLines), 'gehen zurück');
    });
});
