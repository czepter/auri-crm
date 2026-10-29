<?php

use App\Crm\Models\Contact;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Numbering\Models\NumberCounter;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Models\Invoice;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
    Storage::fake('local');
    Bus::fake();
});

it('lists invoices, not rates — with and without a project, newest first', function () {
    $rates = invoiceablePlan()->plan->installments()->orderBy('sort_order')->get();
    $project = app(IssueInvoice::class)($rates);

    $this->travel(1)->minute();
    $free = app(IssueInvoice::class)->positions(
        Contact::factory()->create(['name' => 'Jonas Gast']),
        null,
        [['title' => 'Abzüge', 'quantity' => 30, 'unit_price' => 1_000, 'vat_rate' => 1900]],
        now()->addWeek()->toDateString(),
    );

    $rows = $this->actingAs(staffMember(), 'staff')->get('/rechnungen')
        ->assertOk()->viewData('page')['props']['invoices'];

    expect(collect($rows)->pluck('id')->all())->toBe([$free->public_id, $project->public_id])
        ->and($rows[0]['projectName'])->toBeNull()
        ->and($rows[0]['contact'])->toBe('Jonas Gast')
        ->and($rows[0]['amount'])->toBe(35_700)
        ->and($rows[1]['amount'])->toBe(150_000)
        ->and($rows[1]['openInstallments'])->toHaveCount(2);
});

it('offers the years that have invoices to the export, newest first', function () {
    $this->travelTo('2025-03-01 10:00:00');
    app(IssueInvoice::class)->positions(
        Contact::factory()->create(),
        null,
        [['title' => 'Abzüge', 'quantity' => 1, 'unit_price' => 1_000, 'vat_rate' => 1900]],
        now()->addWeek()->toDateString(),
    );
    $this->travelTo('2026-05-04 10:00:00');
    app(IssueInvoice::class)(invoiceablePlan());

    $years = fn (StaffRole $role) => $this->actingAs(staffMember($role), 'staff')->get('/rechnungen')
        ->assertOk()->viewData('page')['props']['exportYears'];

    expect($years(StaffRole::Admin))->toBe([2026, 2025]);
});

it('refuses a role that may not manage payment plans', function () {
    $role = collect(StaffRole::cases())->first(fn (StaffRole $role) => ! $role->allows(Capability::ManageContracts));

    $this->actingAs(staffMember($role), 'staff')->get('/rechnungen')->assertForbidden();
});

it('offers the open rates of each plan on the new-invoice screen', function () {
    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    $rates = $this->actingAs(staffMember(), 'staff')->get('/rechnungen/neu')
        ->assertOk()->viewData('page')['props']['rates'];

    expect($rates[$installment->plan->case->public_id])->toHaveCount(1)
        ->and($rates[$installment->plan->case->public_id][0]['label'])->toBe('Restbetrag');
});

it('previews an invoice without a project as it will print, drawing no number', function () {
    $html = $this->actingAs(staffMember(), 'staff')
        ->post('/rechnungen/vorschau', [
            'contact' => ['name' => 'Jonas Gast', 'city' => 'Jena'],
            'due_on' => '2026-10-01',
            'items' => [['title' => 'Abzug 30×45', 'quantity' => 30, 'unit_price' => 1_000, 'vat_rate' => 1900]],
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Jonas Gast')
        ->and($html)->toContain('Abzug 30×45')
        ->and($html)->toContain('357,00')
        ->and($html)->toContain('01.10.2026')
        ->and(Invoice::count())->toBe(0)
        ->and(NumberCounter::count())->toBe(0);
});

it('previews the chosen rates of a plan', function () {
    $rates = invoiceablePlan()->plan->installments()->orderBy('sort_order')->get();

    $html = $this->actingAs(staffMember(), 'staff')
        ->post('/rechnungen/vorschau', [
            'case_id' => $rates->first()->plan->case->public_id,
            'items' => $rates->map(fn ($row) => ['title' => 'Rate', 'installment_id' => $row->public_id])->all(),
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('1.500,00')
        ->and($html)->toContain('Lena Meier')
        ->and(Invoice::count())->toBe(0);
});

it('keeps the letter text on the invoice and prints it around the positions', function () {
    $this->actingAs(staffMember(), 'staff')
        ->post('/rechnungen', [
            'contact' => ['name' => 'Jonas Gast'],
            'due_on' => '2026-10-01',
            'intro' => 'Hallo {{ contact.name }},',
            'outro' => 'Viel Freude mit den Bildern.',
            'items' => [['title' => 'Abzüge', 'quantity' => 2, 'unit_price' => 2_000, 'vat_rate' => 1900]],
        ])
        ->assertRedirect();

    $invoice = Invoice::sole();
    $html = app(RenderInvoicePdf::class)->forInvoice($invoice->load('installments', 'items', 'contact'));

    expect($invoice->intro)->toBe('Hallo {{ contact.name }},')
        ->and($html)->toContain('Hallo Jonas Gast,')
        ->and(strpos($html, 'Hallo Jonas Gast,'))->toBeLessThan(strpos($html, 'Abzüge'))
        ->and(strpos($html, 'Viel Freude mit den Bildern.'))->toBeGreaterThan(strpos($html, 'Abzüge'));
});

it('previews the draft as the portal page and as the mail', function () {
    $draft = [
        'contact' => ['name' => 'Jonas Gast'],
        'intro' => 'Hallo Jonas,',
        'items' => [['title' => 'Abzüge', 'quantity' => 2, 'unit_price' => 2_000, 'vat_rate' => 1900]],
    ];

    $page = $this->actingAs(staffMember(), 'staff')
        ->post('/rechnungen/vorschau', [...$draft, 'format' => 'portal'])
        ->assertOk()
        ->json();

    expect($page['intro'])->toBe('Hallo Jonas,')
        ->and($page['lines'][0]['title'])->toBe('Abzüge');

    $this->actingAs(staffMember(), 'staff')
        ->post('/rechnungen/vorschau', [...$draft, 'format' => 'mail'])
        ->assertOk()
        ->assertSee('Hallo Jonas Gast,')
        ->assertSee('Rechnungsbetrag 47,60')
        ->assertDontSee('%%NACHRICHT%%', false)
        ->assertDontSee('abbestellen');
});

it('starts the letter from the invoice template, without the tax line the layout prints', function () {
    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => '<p>Guten Tag {{ contact.name }},</p><p>vielen Dank f&uuml;r den Auftrag.</p>',
    ]);

    $intro = $this->actingAs(staffMember(), 'staff')->get('/rechnungen/neu')
        ->assertOk()->viewData('page')['props']['intro'];

    expect($intro)->toBe('<p>Guten Tag {{ contact.name }},</p><p>vielen Dank für den Auftrag.</p>');
});

it('prints the Steuernummer from the layout whatever the letter says', function () {
    $invoice = app(IssueInvoice::class)(invoiceablePlan(), null, ['intro' => 'Hallo.'])->fresh();

    $html = app(RenderInvoicePdf::class)->forInvoice($invoice);

    expect($html)->toContain('Steuernummer')
        ->and($html)->toContain('22/123/45678')
        ->and($html)->toContain('Hallo.');
});

it('keeps only letter markup and escapes what a merge field brings in', function () {
    $contact = Contact::factory()->create(['name' => '<b>Jonas</b>']);

    $this->actingAs(staffMember(), 'staff')
        ->post('/rechnungen', [
            'contact_id' => $contact->public_id,
            'due_on' => '2026-10-01',
            'intro' => '<h2>Hallo {{ contact.name }}</h2><script>alert(1)</script><p onclick="x()">Danke</p>',
            'items' => [['title' => 'Abzüge', 'quantity' => 1, 'unit_price' => 2_000, 'vat_rate' => 1900]],
        ])
        ->assertRedirect();

    $invoice = Invoice::sole();
    $html = app(RenderInvoicePdf::class)->forInvoice($invoice->load('installments', 'items', 'contact'));

    expect($invoice->intro)->toBe('<h2>Hallo {{ contact.name }}</h2><p>Danke</p>')
        ->and($html)->toContain('<h2>Hallo &lt;b&gt;Jonas&lt;/b&gt;</h2>');
});
