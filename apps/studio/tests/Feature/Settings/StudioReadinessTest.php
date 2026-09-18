<?php

use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioReadiness;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function readiness(array $attributes): StudioReadiness
{
    StudioSettings::query()->update($attributes);
    Cache::store('redis')->flush();

    return new StudioReadiness(new StudioSettingsResolver);
}

it('names the Impressum fields the first quote needs', function () {
    $missing = readiness(['legal_name' => null, 'city' => 'Hamburg'])->missingForQuote();

    expect($missing)->toContain('legal_name')
        ->and($missing)->not->toContain('city');
});

it('takes either Steuernummer or USt-IdNr. for the first invoice', function () {
    $both = readiness(['tax_number' => null, 'vat_id' => null])->missingForInvoice();
    $one = readiness(['tax_number' => null, 'vat_id' => 'DE123456789'])->missingForInvoice();

    expect($both)->toContain('tax_number')
        ->and($one)->not->toContain('tax_number');
});

it('asks for the register pair only where the legal form is registered', function () {
    $gmbh = readiness(['legal_form' => 'GmbH', 'register_number' => null, 'register_court' => null])->missingForInvoice();
    $sole = readiness(['legal_form' => 'Einzelunternehmen', 'register_number' => null, 'register_court' => null])->missingForInvoice();

    expect($gmbh)->toContain('register_number', 'register_court')
        ->and($sole)->not->toContain('register_number');
});
