<?php

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

function csvUpload(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('kunden.csv', $content);
}

function previewImport(string $content, ?array $mapping = null)
{
    return test()->actingAs(test()->staff, 'staff')
        ->postJson('/kontakte/import/vorschau', array_filter(['file' => csvUpload($content), 'mapping' => $mapping]));
}

it('reads semicolon files with a byte order mark and suggests the mapping from the header', function () {
    $content = "\u{FEFF}Vorname;Nachname;E-Mail;Telefon;PLZ;Ort;Instagram\nAnna;Brandt;anna@example.test;0170 1234567;22765;Hamburg;anna.brandt\n";

    $response = previewImport($content)->assertOk();

    expect($response->json('mapping'))->toBe(['first_name', 'last_name', 'email', 'phone', 'postcode', 'city', 'instagram'])
        ->and($response->json('rows.0'))->toMatchArray(['line' => 2, 'name' => 'Anna Brandt', 'status' => 'create']);
});

it('reads comma files and Windows-1252 umlauts', function () {
    $content = mb_convert_encoding("Name,Straße,Ort\nJürgen Müller,Königstraße 1,Köln\n", 'Windows-1252', 'UTF-8');

    $response = previewImport($content)->assertOk();

    expect($response->json('headers'))->toBe(['Name', 'Straße', 'Ort'])
        ->and($response->json('mapping'))->toBe(['name', 'street', 'city'])
        ->and($response->json('rows.0.name'))->toBe('Jürgen Müller');
});

it('skips known and repeated addresses and reports invalid rows with their reason', function () {
    Contact::factory()->create(['email' => 'known@example.test']);

    $content = "Name;E-Mail;Telefon\n"
        ."Bekannt;KNOWN@example.test;\n"
        ."Neu;neu@example.test;\n"
        ."Neu Nochmal;neu@example.test;\n"
        ."Kaputt;kaputt;\n"
        .";ohne@example.test;\n"
        ."Ohne Adresse;;abc\n";

    $rows = previewImport($content)->assertOk()->json('rows');

    expect(array_column($rows, 'status'))->toBe(['existing', 'create', 'repeated', 'invalid', 'invalid', 'invalid'])
        ->and($rows[3]['reason'])->toContain('E-Mail')
        ->and($rows[4]['reason'])->toContain('Name');
});

it('creates exactly the valid rows in one audited import', function () {
    Contact::factory()->create(['email' => 'known@example.test']);

    $content = "Name;E-Mail;Instagram\nBekannt;known@example.test;\nNeu;neu@example.test;@neu\nKaputt;kaputt;\nOhne Adresse;;\n";

    $this->actingAs($this->staff, 'staff')
        ->post('/kontakte/import', ['file' => csvUpload($content), 'mapping' => ['name', 'email', 'instagram']])
        ->assertRedirect('/kontakte');

    expect(Contact::pluck('name')->sort()->values()->all())->toContain('Neu', 'Ohne Adresse')->not->toContain('Kaputt')
        ->and(Contact::count())->toBe(3)
        ->and(Contact::firstWhere('name', 'Neu')->social_profiles)->toEqual([['platform' => 'instagram', 'value' => '@neu']])
        ->and(AuditLog::where('event', 'contact.created')->count())->toBe(0)
        ->and(AuditLog::where('event', 'contact.imported')->sole()->context)->toEqual(['created' => 2, 'skipped' => 1, 'invalid' => 1]);
});

it('keeps the import off contacts a role may not edit', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Reader]), 'staff')
        ->postJson('/kontakte/import/vorschau', ['file' => csvUpload("Name\nAnna\n")])
        ->assertForbidden();
});
