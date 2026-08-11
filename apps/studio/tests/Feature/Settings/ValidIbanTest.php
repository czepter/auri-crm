<?php

use App\Settings\Rules\ValidIban;

it('accepts a real IBAN by its mod-97 checksum, not merely its length', function () {
    expect(ValidIban::checksumPasses('DE89 3704 0044 0532 0130 00'))->toBeTrue();
});

it('rejects an IBAN with a transposed digit pair — same length, same character set, wrong checksum', function () {
    // The two digits at positions 15–16 of the valid IBAN above ("01") are
    // swapped to "10" — the exact failure mode a bank rejects and a
    // length/regex-only check would miss.
    expect(ValidIban::checksumPasses('DE89 3704 0044 0532 1030 00'))->toBeFalse();
});

it('rejects a string that is not IBAN-shaped at all', function () {
    expect(ValidIban::checksumPasses('not an iban'))->toBeFalse()
        ->and(ValidIban::checksumPasses(''))->toBeFalse();
});

it('fails validation with a field-level message for a checksum-invalid IBAN', function () {
    $rule = new ValidIban;
    $failed = null;

    $rule->validate('bank_iban', 'DE89 3704 0044 0532 1030 00', function (string $message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->not->toBeNull();
});

it('passes validation with no failure for a checksum-valid IBAN', function () {
    $rule = new ValidIban;
    $failed = null;

    $rule->validate('bank_iban', 'DE89 3704 0044 0532 0130 00', function (string $message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->toBeNull();
});
