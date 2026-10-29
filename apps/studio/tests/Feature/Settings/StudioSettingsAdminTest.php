<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Settings\Enums\FontPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// staffMember() and props() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

/**
 * The bank fieldset is optional on save — this is the valid one every test
 * below that wants bank details stored merges in, rather than repeating a
 * checksum-valid IBAN per case.
 *
 * Named t07ValidBankFields rather than the generic validBankFields: a global
 * function declared in one test file collides the moment a second file
 * declares the same name (see tests/Pest.php's own note on this), and
 * several sibling settings stories are landing test files in this same
 * directory in parallel.
 */
function t07ValidBankFields(): array
{
    return [
        'bank_account_holder' => 'Atelier Nordlicht Fotografie GmbH',
        'bank_iban' => 'DE89 3704 0044 0532 0130 00',
        'bank_bic' => 'COBADEFFXXX',
    ];
}

it('renders the settings shell for an owner', function () {
    $props = $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/einstellungen/allgemein')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings']['id'])->toBe(StudioSettings::query()->sole()->public_id)
        ->and($props['settings'])->toHaveKey('displayName')
        ->and($props['settings'])->toHaveKey('legalName')
        ->and($props['settings'])->toHaveKey('bankIban')
        ->and($props['settings'])->toHaveKeys(['accentColor', 'fontPair', 'clientRegister', 'taxNumber', 'vatId', 'registerNumber', 'timezone'])
        ->and($props)->toHaveKey('fontPairs')
        ->and($props['settings']['logoUrl'])->toBeNull();
});

it('sends the brand and legal-entity paths to Allgemein', function (string $path) {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get($path)
        ->assertRedirect('/einstellungen/allgemein');
})->with(['/einstellungen/marke', '/einstellungen/rechtliche-einheit']);

it('saves the form of address and the timezone with the rest of the profile', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch('/einstellungen', ['display_name' => 'Atelier Nordlicht', 'client_register' => 'informal', 'timezone' => 'Europe/Vienna'])
        ->assertSessionHasNoErrors();

    $row = StudioSettings::query()->sole();

    expect($row->client_register)->toBe('informal')
        ->and($row->timezone)->toBe('Europe/Vienna');

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch('/einstellungen', ['timezone' => 'Nonsense/Place'])
        ->assertInvalid(['timezone']);
});

it('sends the settings root to the first pane', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/einstellungen')
        ->assertRedirect('/einstellungen/allgemein');
});

it('serves every settings pane on its own path', function (string $path, string $pane) {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pane', $pane));
})->with([
    ['/einstellungen/allgemein', 'general'],
    ['/einstellungen/email', 'email'],
    ['/einstellungen/zahlung', 'payment'],
    ['/einstellungen/belege', 'receipts'],
]);

it('sends the former billing addresses to their new pages', function (string $from, string $to) {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get($from)
        ->assertRedirect($to);
})->with([
    ['/einstellungen/rechnungsdaten', '/einstellungen/zahlung'],
    ['/einstellungen/dokumente', '/einstellungen/belege'],
]);

it('updates the display name, redirects, and leaves an audit row naming the owner', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen')
        ->patch('/einstellungen', [
            'display_name' => 'Atelier Nordlicht', 'tax_number' => '22/123/45678',
            ...t07ValidBankFields(),
        ])
        ->assertRedirect('/einstellungen');

    expect(StudioSettings::query()->sole()->display_name)->toBe('Atelier Nordlicht')
        ->and(AuditLog::where('event', 'studio_settings.updated')->where('context->key', 'display_name')->sole()->actor_id)->toBe($owner->id);
});

it('saves the full legal-entity, brand and bank fieldset in one submit', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')->from('/einstellungen')->patch('/einstellungen', [
        'display_name' => 'Atelier Nordlicht',
        'legal_name' => 'Atelier Nordlicht Fotografie GmbH',
        'legal_form' => 'GmbH',
        'street' => 'Elbchaussee 112',
        'postcode' => '22763',
        'city' => 'Hamburg',
        'country' => 'Deutschland',
        'tax_number' => '22/123/45678',
        'vat_id' => 'DE284957312',
        'register_number' => 'HRB 142 887',
        'register_court' => 'Amtsgericht Hamburg',
        'representative' => 'Christian Nordlicht',
        'website' => 'https://atelier-nordlicht.de',
        'accent_color' => '#B44E22',
        ...t07ValidBankFields(),
    ])->assertRedirect('/einstellungen');

    $row = StudioSettings::query()->sole();

    expect($row->legal_name)->toBe('Atelier Nordlicht Fotografie GmbH')
        ->and($row->legal_form)->toBe('GmbH')
        ->and($row->street)->toBe('Elbchaussee 112')
        ->and($row->postcode)->toBe('22763')
        ->and($row->city)->toBe('Hamburg')
        ->and($row->country)->toBe('Deutschland')
        ->and($row->tax_number)->toBe('22/123/45678')
        ->and($row->vat_id)->toBe('DE284957312')
        ->and($row->register_number)->toBe('HRB 142 887')
        ->and($row->register_court)->toBe('Amtsgericht Hamburg')
        ->and($row->representative)->toBe('Christian Nordlicht')
        ->and($row->website)->toBe('https://atelier-nordlicht.de')
        ->and($row->accent_color)->toBe('#B44E22');
});

it('rejects saving with neither Steuernummer nor USt-IdNr present', function () {
    $owner = staffMember(StaffRole::Admin);

    $response = $this->actingAs($owner, 'staff')
        ->from('/einstellungen')
        ->patch('/einstellungen', ['tax_number' => '', 'vat_id' => '']);

    $response->assertRedirect('/einstellungen')
        ->assertSessionHasErrors(['tax_number', 'vat_id']);

    expect(StudioSettings::query()->sole()->tax_number)->toBeNull();
});

it('saves the Marke fieldset alone without demanding the tax fields it does not show', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen')
        ->patch('/einstellungen', ['display_name' => 'Atelier Nordlicht', 'accent_color' => '#B44E22'])
        ->assertRedirect('/einstellungen')
        ->assertSessionHasNoErrors();

    $row = StudioSettings::query()->sole();

    expect($row->display_name)->toBe('Atelier Nordlicht')
        ->and($row->tax_number)->toBeNull();
});

it('accepts saving with only the USt-IdNr present', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', ['vat_id' => 'DE284957312', ...t07ValidBankFields()])
        ->assertSessionHasNoErrors();

    expect(StudioSettings::query()->sole()->vat_id)->toBe('DE284957312');
});

it('rejects an IBAN with a transposed digit pair on save, with a field-level message', function () {
    $owner = staffMember(StaffRole::Admin);

    $response = $this->actingAs($owner, 'staff')
        ->from('/einstellungen')
        ->patch('/einstellungen', [
            'tax_number' => '22/123/45678',
            'bank_account_holder' => 'Atelier Nordlicht Fotografie GmbH',
            'bank_iban' => 'DE89 3704 0044 0532 1030 00', // transposed pair, see ValidIbanTest
            'bank_bic' => 'COBADEFFXXX',
        ]);

    $response->assertRedirect('/einstellungen')->assertSessionHasErrors(['bank_iban']);

    expect(StudioSettings::query()->sole()->bank_iban)->toBeNull();
});

it('accepts and stores a checksum-valid IBAN', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', [
            'tax_number' => '22/123/45678',
            'bank_account_holder' => 'Atelier Nordlicht Fotografie GmbH',
            'bank_iban' => 'DE89 3704 0044 0532 0130 00',
            'bank_bic' => 'COBADEFFXXX',
        ])
        ->assertSessionHasNoErrors();

    expect(StudioSettings::query()->sole()->bank_iban)->toBe('DE89 3704 0044 0532 0130 00');
});

it('saves without bank details — the fieldset is optional, the portal hides "Überweisen" when there is no IBAN', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen')
        ->patch('/einstellungen', ['tax_number' => '22/123/45678'])
        ->assertRedirect('/einstellungen')
        ->assertSessionHasNoErrors();

    expect(StudioSettings::query()->sole()->bank_iban)->toBeNull();
});

it('stores an uploaded logo once and serves it from one route, with an audit row', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', [
            'tax_number' => '22/123/45678',
            'logo' => UploadedFile::fake()->image('logo.png'),
            ...t07ValidBankFields(),
        ])
        ->assertSessionHasNoErrors();

    $row = StudioSettings::query()->sole();
    expect($row->logo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($row->logo_path);

    expect(AuditLog::where('event', 'studio_settings.updated')->where('context->key', 'logo_path')->sole()->actor_id)
        ->toBe($owner->id);

    $props = $this->get('/einstellungen/allgemein')->assertOk()->viewData('page')['props'];
    expect($props['settings']['logoUrl'])->not->toBeNull();

    $this->get($props['settings']['logoUrl'])->assertOk();
});

// Vier Formate, weil ein Studio sein Zeichen als Vektor hat, wenn es Glück
// hat. SVG geht nur mit den Kopfzeilen darunter.
it('takes a logo as PNG, JPEG, WebP or SVG', function (string $file, string $mime) {
    Storage::fake('local');

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch('/einstellungen', [
            'tax_number' => '22/123/45678',
            'logo' => $mime === 'image/svg+xml'
                ? UploadedFile::fake()->create($file, 12, $mime)
                : UploadedFile::fake()->image($file, 40, 40),
            ...t07ValidBankFields(),
        ])
        ->assertSessionHasNoErrors();

    expect(StudioSettings::query()->sole()->logo_path)->not->toBeNull();
})->with([
    ['logo.png', 'image/png'],
    ['logo.jpg', 'image/jpeg'],
    ['logo.webp', 'image/webp'],
    ['logo.svg', 'image/svg+xml'],
]);

// Ein Kamera-Export soll nicht in voller Größe auf jedem Beleg liegen; SVG
// wird nicht angefasst.
it('shrinks a large logo to 800 px and leaves an SVG alone', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', ['tax_number' => '22/123/45678', 'logo' => UploadedFile::fake()->image('logo.png', 2400, 1200), ...t07ValidBankFields()])
        ->assertSessionHasNoErrors();

    $path = StudioSettings::query()->sole()->logo_path;
    expect(getimagesizefromstring(Storage::disk('local')->get($path))[0])->toBe(800);

    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', ['tax_number' => '22/123/45678', 'logo' => $svg, ...t07ValidBankFields()])
        ->assertSessionHasNoErrors();

    expect(Storage::disk('local')->get(StudioSettings::query()->sole()->logo_path))->toContain('<svg');
});

it('refuses a logo that is not an image at all', function () {
    Storage::fake('local');

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch('/einstellungen', [
            'tax_number' => '22/123/45678',
            'logo' => UploadedFile::fake()->create('logo.pdf', 12, 'application/pdf'),
            ...t07ValidBankFields(),
        ])
        ->assertInvalid(['logo']);
});

// Eine SVG-Datei ist Markup: ohne diese Kopfzeilen liefe ein <script> darin
// auf unserer Herkunft, sobald jemand die Adresse direkt öffnet.
it('serves the logo sandboxed, so an SVG cannot run as this app', function () {
    Storage::fake('local');

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')->patch('/einstellungen', [
        'tax_number' => '22/123/45678',
        'logo' => UploadedFile::fake()->create('logo.svg', 12, 'image/svg+xml'),
        ...t07ValidBankFields(),
    ]);

    $this->get('/einstellungen/logo')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
});

it('replaces the logo on a second upload rather than keeping both', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')->patch('/einstellungen', [
        'tax_number' => '22/123/45678',
        'logo' => UploadedFile::fake()->image('first.png'),
        ...t07ValidBankFields(),
    ]);
    $firstPath = StudioSettings::query()->sole()->logo_path;

    $this->actingAs($owner, 'staff')->patch('/einstellungen', [
        'tax_number' => '22/123/45678',
        'logo' => UploadedFile::fake()->image('second.png'),
        ...t07ValidBankFields(),
    ]);
    $secondPath = StudioSettings::query()->sole()->logo_path;

    Storage::disk('local')->assertExists($secondPath);
    expect($firstPath)->toBe($secondPath); // fixed filename per tenant — overwritten, not duplicated
});

it('opens the settings screen to the owner, and gives every other role the same response as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')->get('/einstellungen/allgemein');

    $response->assertStatus($status);

    if ($status === 404) {
        expect($response->status())->toBe($missing->status());
    }
})->with([
    [StaffRole::Admin, 200],
    [StaffRole::Manager, 404],
    [StaffRole::Contributor, 404],
    [StaffRole::Contributor, 404],
    [StaffRole::Contributor, 404],
]);

it('declares an audience on the settings routes, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $settingsRoutes = collect($routes)->filter(fn ($route) => $route->uri() === 'einstellungen');

    expect($settingsRoutes)->toHaveCount(2)
        ->and($settingsRoutes->every(
            fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:'))
        ))->toBeTrue();
});

it('lists Einstellungen in the sidebar only for a role that manages settings', function () {
    $owner = $this->actingAs(staffMember(StaffRole::Admin), 'staff')->get('/')->assertOk();
    expect($owner->viewData('page')['props']['auth']['can'])->toContain('manage_settings');

    $photographer = $this->actingAs(staffMember(StaffRole::Manager), 'staff')->get('/')->assertOk();
    expect($photographer->viewData('page')['props']['auth']['can'])->not->toContain('manage_settings');
});

// #234: an enum, never free text — the cases are the pairs this product has
// licensed and self-hosts, and a fifth name would be a font nobody may serve.
it('stores the chosen font pair and refuses one that is not licensed', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->from('/einstellungen')
        ->patch('/einstellungen', [
            'display_name' => 'Atelier Nordlicht', 'tax_number' => '22/123/45678',
            'font_pair' => 'fraunces', ...t07ValidBankFields(),
        ])
        ->assertRedirect('/einstellungen');

    expect(FontPair::current())->toBe(FontPair::Fraunces);

    $this->patch('/einstellungen', [
        'display_name' => 'Atelier Nordlicht', 'tax_number' => '22/123/45678',
        'font_pair' => 'helvetica', ...t07ValidBankFields(),
    ])->assertInvalid(['font_pair']);
});

// A studio that never opens the setting keeps the product default.
it('falls back to Hanken while nothing is chosen', function () {
    expect(FontPair::current())->toBe(FontPair::Hanken);
});

it('rejects a malformed BIC, USt-IdNr. and Steuernummer', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', ['bank_bic' => 'BYLA', 'vat_id' => 'DE81', 'tax_number' => '43/12'])
        ->assertSessionHasErrors(['bank_bic', 'vat_id', 'tax_number']);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', ['bank_bic' => 'BYLADEM1001', 'vat_id' => 'DE812345678', 'tax_number' => '43/123/45678'])
        ->assertSessionHasNoErrors();
});
