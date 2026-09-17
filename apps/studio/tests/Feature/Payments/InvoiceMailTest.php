<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Notifications\InvoiceSent;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(StudioSettingsResolver::class)->set('tax_number', '22/123/45678', null);
});

/** An issued invoice whose PDF is on the faked disk. */
function mailableInvoice()
{
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => 'Rechnung {{ invoice.number }}',
    ]);

    test()->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->andReturn('%PDF-1.4 fake');

    return app(IssueInvoice::class)(invoiceablePlan())->fresh();
}

it('sends the issue button into the compose screen', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/raten/{$installment->public_id}/rechnung")
        ->assertRedirect('/rechnungen/'.$installment->fresh()->invoice->public_id.'/versenden');
});

it('renders the compose screen with the recipient and the invoice card data', function () {
    $invoice = mailableInvoice();

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/rechnungen/{$invoice->public_id}/versenden")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['invoice']['number'])->toBe($invoice->number)
        ->and($props['invoice']['amount'])->toBe($invoice->amount())
        ->and($props['invoice']['statusLabel'])->toBe(__('payments.invoiceStatus.'.$invoice->status()))
        ->and($props['recipients'][0]['email'])->toBe('lena@example.test')
        ->and($props['mailHtml'])->toContain('%%NACHRICHT%%');
});

it('attaches the PDF to the mail and stamps sent_at once', function () {
    Notification::fake();

    $invoice = mailableInvoice();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/rechnungen/{$invoice->public_id}/versenden", [
            'subject' => "Rechnung {$invoice->number}",
            'message' => "Hallo Lena,\n\nanbei die Rechnung.",
        ])
        ->assertRedirect('/rechnungen');

    Notification::assertSentTo(
        $invoice->project->primaryContact(),
        InvoiceSent::class,
        function (InvoiceSent $notification, array $channels, $notifiable) use ($invoice) {
            $mail = $notification->toMail($notifiable);

            expect($mail->rawAttachments[0]['name'])->toBe("rechnung-{$invoice->number}.pdf")
                ->and($mail->rawAttachments[0]['data'])->toBe('%PDF-1.4 fake');

            return true;
        },
    );

    $sentAt = $invoice->fresh()->sent_at;
    expect($sentAt)->not->toBeNull();

    $this->travel(1)->day();
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/rechnungen/{$invoice->public_id}/versenden", ['subject' => 'Nochmal']);

    expect($invoice->fresh()->sent_at->equalTo($sentAt))->toBeTrue();
});
