<?php

use App\Crm\Models\Contact;
use App\Crm\Support\ContactCard;

function vcardFor(array $attributes): string
{
    $contact = (new Contact($attributes))->forceFill([
        'public_id' => 'c_test',
        'updated_at' => now()->setDate(2026, 11, 15)->setTime(10, 0),
    ]);

    return (new ContactCard)->render($contact);
}

it('escapes comma, semicolon, backslash and newline', function () {
    $body = vcardFor(['name' => 'Meier, Anna; Studio\Test', 'street' => "Weg 1\nHinterhaus"]);

    expect($body)->toContain('FN:Meier\, Anna\; Studio\\\\Test')
        ->and($body)->toContain('N:Studio\\\\Test;Meier\, Anna\;;;;')
        ->and($body)->toContain('ADR;TYPE=HOME:;;Weg 1\nHinterhaus;');
});

it('renders a name-only contact without empty properties', function () {
    $body = vcardFor(['name' => 'Madonna', 'email' => '  ', 'phone' => null]);

    expect($body)->toContain("N:Madonna;;;;\r\n")
        ->and($body)->not->toContain('EMAIL')
        ->and($body)->not->toContain('TEL')
        ->and($body)->not->toContain('ADR');
});

it('splits the name on the last word', function () {
    expect(vcardFor(['name' => 'Anna & Ben Meier']))->toContain("N:Meier;Anna & Ben;;;\r\n");
});

it('folds long lines with crlf', function () {
    $body = vcardFor(['name' => 'Anna Meier', 'street' => str_repeat('Ä', 60)]);

    expect($body)->toContain("\r\n ")
        ->and(collect(explode("\r\n", $body))->every(fn ($line) => strlen($line) <= 75))->toBeTrue();
});

it('drops control characters and turns a bare carriage return into a newline escape', function () {
    $body = vcardFor(['name' => "Anna\x01\u{FFFF}\u{FFFE} Meier\rEND:VCARD\rBEGIN:VCARD"]);

    expect($body)->not->toMatch('/[\x00-\x08\x0B\x0C\x0E-\x1F]/')
        ->and($body)->not->toContain("\u{FFFF}")->not->toContain("\u{FFFE}")
        ->and($body)->not->toMatch('/\r(?!\n)/')
        ->and($body)->toContain('FN:Anna Meier\nEND:VCARD\nBEGIN:VCARD')
        ->and(preg_match_all('/^BEGIN:VCARD/m', $body))->toBe(1);
});
