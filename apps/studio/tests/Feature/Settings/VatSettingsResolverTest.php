<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('seeds vat_mode standard, a 19% rate in basis points, and the standard §19 note', function () {
    $row = StudioSettings::query()->sole();

    expect($row->vat_mode)->toBe('standard')
        ->and($row->vat_rate)->toBe(1900)
        ->and($row->vat_note)->toBe('Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');
});

it('resolves vat_mode, vat_rate and vat_note through the resolver', function () {
    $resolver = app(StudioSettingsResolver::class);

    expect($resolver->vatMode())->toBe('standard')
        ->and($resolver->vatRate())->toBe(1900)
        ->and($resolver->vatNote())->toBe('Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');
});

it('returns the stored rate as the effective rate under standard mode', function () {
    $resolver = new StudioSettingsResolver;

    expect($resolver->effectiveVatRate())->toBe(1900);
});

it('forces the effective rate to zero under small_business, regardless of what vat_rate holds', function () {
    $owner = staffMember(StaffRole::Admin);
    $resolver = new StudioSettingsResolver;

    // A deliberately non-zero, non-default rate left on the row — proving
    // effectiveVatRate() ignores it entirely under small_business rather
    // than happening to return zero because vat_rate was already zero.
    $resolver->set('vat_rate', 2100, $owner);
    $resolver->set('vat_mode', 'small_business', $owner);

    expect($resolver->effectiveVatRate())->toBe(0)
        ->and($resolver->vatRate())->toBe(2100);
});

it('reverts to the stored rate the moment mode switches back to standard', function () {
    $owner = staffMember(StaffRole::Admin);
    $resolver = new StudioSettingsResolver;

    $resolver->set('vat_rate', 2100, $owner);
    $resolver->set('vat_mode', 'small_business', $owner);
    expect($resolver->effectiveVatRate())->toBe(0);

    $resolver->set('vat_mode', 'standard', $owner);

    // vat_rate was never touched by the small_business switch — switching
    // back does not lose it, per the issue's own scope line.
    expect($resolver->effectiveVatRate())->toBe(2100);
});

it('writes one audit row per changed VAT field', function () {
    $owner = staffMember(StaffRole::Admin);
    $resolver = new StudioSettingsResolver;

    $resolver->set('vat_mode', 'small_business', $owner);
    $resolver->set('vat_note', 'Individueller Hinweistext.', $owner);

    expect(AuditLog::where('event', 'studio_settings.updated')->count())->toBe(2)
        ->and(AuditLog::where('context->key', 'vat_mode')->sole()->context['new'])->toBe('small_business')
        ->and(AuditLog::where('context->key', 'vat_note')->sole()->context['new'])->toBe('Individueller Hinweistext.');
});
