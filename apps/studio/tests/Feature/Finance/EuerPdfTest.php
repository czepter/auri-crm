<?php

use App\Finance\Actions\LockFiscalYear;
use App\Finance\Models\Expense;
use App\Finance\Models\FiscalYear;
use App\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-22 10:00:00');
    Storage::fake('s3');
    $this->admin = staffMember();
});

it('renders an open year live, with the figures and escaped values', function () {
    Expense::factory()->create(['vendor' => '<script>alert(1)</script> GmbH', 'paid_at' => '2026-02-01']);
    $html = null;
    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->once()->andReturnUsing(function (string $page, bool $cssPageSize) use (&$html) {
        $html = $cssPageSize ? $page : null;

        return '%PDF-1.4 live';
    });

    $response = $this->actingAs($this->admin, 'staff')->get('/finanzen/euer/2026/pdf')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->getContent())->toBe('%PDF-1.4 live')
        ->and($html)->toContain('Einnahmenüberschussrechnung 2026', '&lt;script&gt;alert(1)&lt;/script&gt; GmbH')
        ->and($html)->not->toContain('<script>');
});

it('streams a locked year’s stored PDF and makes it from the snapshot when it is missing', function () {
    Expense::factory()->create(['paid_at' => '2025-02-01']);
    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->twice()->andReturn('%PDF-1.4 snapshot');
    app(LockFiscalYear::class)(2025, $this->admin);

    $fiscal = FiscalYear::query()->where('year', 2025)->sole();
    expect($fiscal->pdf_path)->toBe("finance/{$fiscal->tenant_id}/euer-2025.pdf");
    Storage::disk('s3')->assertExists($fiscal->pdf_path);

    $this->actingAs($this->admin, 'staff')->get('/finanzen/euer/2025/pdf')->assertOk()->assertSee('%PDF-1.4 snapshot', false);

    Storage::disk('s3')->delete($fiscal->pdf_path);
    $fiscal->update(['pdf_path' => null]);

    $this->get('/finanzen/euer/2025/pdf')->assertOk()->assertSee('%PDF-1.4 snapshot', false);
    expect($fiscal->fresh()->pdf_path)->not->toBeNull();
});

it('shows the report page for the selected year', function () {
    Expense::factory()->create(['paid_at' => '2026-02-01', 'gross_eur' => 11900]);

    $page = $this->actingAs($this->admin, 'staff');
    $props = props('/finanzen/euer?jahr=2026');

    expect($props['report']['year'])->toBe(2026)
        ->and($props['report']['paidInputVat'])->toBe(1900)
        ->and($props['lockable'])->toBeFalse()
        ->and(props('/finanzen/euer?jahr=2025')['lockable'])->toBeTrue();
});

it('offers the year being looked at even when it has no data', function () {
    $this->actingAs($this->admin, 'staff');

    foreach (['/finanzen/ausgaben', '/finanzen/anlagen', '/finanzen/euer'] as $path) {
        expect(props("$path?jahr=2015")['years'])->toContain(2015);
    }
});
