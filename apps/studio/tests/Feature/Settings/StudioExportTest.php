<?php

use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\Asset;
use App\Models\AuditLog;
use App\Notifications\Models\InAppNotification;
use App\Payments\Actions\IssueCreditNote;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Actions\PackStudioExport;
use App\Settings\Actions\RequestStudioExport;
use App\Settings\Enums\StudioExportKind;
use App\Settings\Jobs\BuildStudioExport;
use App\Settings\Models\StudioExport;
use App\Settings\Notifications\StudioExportReady;
use App\Settings\Support\StudioExportTables;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
    app(StudioSettingsResolver::class)->set('tax_number', '22/123/45678', null);
});

/** The archive the fake bucket received, opened. */
function openedExport(array $captured): ZipArchive
{
    $tmp = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($tmp, assembledUpload($captured, array_key_first($captured)));

    $zip = new ZipArchive;
    $zip->open($tmp);
    unlink($tmp);

    return $zip;
}

function packedExport(StudioExport $export): array
{
    $captured = [];
    $aborted = [];
    app()->instance(S3Client::class, zipMultipartMock($captured, $aborted));

    app(PackStudioExport::class)($export);

    return $captured;
}

it('packs contacts, projects, documents and messages into the Daten archive', function () {
    $owner = staffMember(StaffRole::Admin);
    $installment = creditableInstallment();
    $invoice = $installment->invoice;
    $project = $invoice->case;

    Storage::disk('local')->put('invoices/rechnung.pdf', '%PDF-invoice');
    $invoice->update(['pdf_path' => 'invoices/rechnung.pdf']);

    $note = app(IssueCreditNote::class)($invoice, 25_000, 'Nachlass');
    Storage::disk('local')->put('credit-notes/gutschrift.pdf', '%PDF-credit');
    $note->update(['pdf_path' => 'credit-notes/gutschrift.pdf']);

    Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent, 'number' => 'AN-2026-7', 'sent_at' => now(), 'net' => 100_000, 'vat_amount' => 19_000]);
    Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Draft]);
    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->once()->andReturn('%PDF-quote');

    $thread = Thread::factory()->for($project, 'case')->create(['subject' => 'Frage zum Ablauf']);
    $message = Message::factory()->for($thread)->create(['body' => 'Wann ist der Termin?']);

    $export = app(RequestStudioExport::class)(StudioExportKind::Daten, null, $owner);
    $zip = openedExport(packedExport($export));

    $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->sort()->values()->all();
    $year = $invoice->issued_at->year;

    expect($names)->toBe([
        'angebote/'.now()->year.'/angebot-AN-2026-7.pdf',
        'angebote/angebote.csv',
        'gutschriften/'.$note->issued_at->year.'/gutschrift-'.$note->number.'.pdf',
        'gutschriften/gutschriften.csv',
        'kontakte.csv',
        'nachrichten.json',
        'projekte.csv',
        "rechnungen/{$year}/rechnung-{$invoice->number}.pdf",
        'rechnungen/rechnungen.csv',
    ])
        ->and($zip->getFromName("rechnungen/{$year}/rechnung-{$invoice->number}.pdf"))->toBe('%PDF-invoice')
        ->and($zip->getFromName('kontakte.csv'))->toContain($project->contacts()->first()->name)
        ->and($zip->getFromName('projekte.csv'))->toContain($project->name)
        ->and($zip->getFromName('angebote/angebote.csv'))->toContain('AN-2026-7;')->toContain('1190,00');

    $rows = array_map(fn ($line) => str_getcsv($line, ';'), explode("\n", trim(ltrim($zip->getFromName('rechnungen/rechnungen.csv'), "\u{FEFF}"))));
    expect($rows[0])->toBe(['Nummer', 'Datum', 'Betrag', 'Kontakt'])
        ->and($rows[1][0])->toBe($invoice->number)
        ->and($rows[1][2])->toBe('750,00');

    $messages = json_decode($zip->getFromName('nachrichten.json'), true);
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['id'])->toBe($thread->public_id)
        ->and($messages[0]['messages'][0]['author'])->toBe($message->contact->public_id)
        ->and(integerIdPaths($messages))->toBe([]);

    $export->refresh();
    expect($export->status)->toBe(ZipBuildStatus::Ready)
        ->and($export->path)->toBe("exports/{$export->tenant_id}/{$export->public_id}.zip")
        ->and($export->expires_at->isFuture())->toBeTrue()
        ->and(InAppNotification::query()->where('type', StudioExportReady::class)->where('notifiable_id', $owner->id)->sole()->data['url'])->toBe('/einstellungen/export');
});

it('packs the originals of one gallery with their numbers', function () {
    $owner = staffMember(StaffRole::Admin);
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit']);
    foreach (['IMG_1.jpg' => 'aaa', 'IMG_2.jpg' => 'bbbb'] as $filename => $bytes) {
        $asset = Asset::factory()->for($gallery)->create([
            'filename' => $filename,
            'byte_size' => strlen($bytes),
            'status' => AssetStatus::Ready,
            'key' => "galleries/t/{$gallery->public_id}/{$filename}",
            'taken_at' => $filename === 'IMG_1.jpg' ? now()->subMinute() : now(),
        ]);
        Storage::disk('s3')->put($asset->key, $bytes);
    }

    $export = app(RequestStudioExport::class)(StudioExportKind::Galerie, $gallery, $owner);
    $zip = openedExport(packedExport($export));

    expect($zip->numFiles)->toBe(3)
        ->and($zip->getFromName('IMG_2.jpg'))->toBe('bbbb')
        ->and(ltrim($zip->getFromName('bilder.csv'), "\u{FEFF}"))->toBe("Bildnummer;Dateiname\n1;IMG_1.jpg\n2;IMG_2.jpg\n");
});

it('marks the export failed when a member cannot be read', function () {
    $owner = staffMember(StaffRole::Admin);
    $gallery = Gallery::factory()->create();
    Asset::factory()->for($gallery)->create(['status' => AssetStatus::Ready, 'key' => 'galleries/t/missing.jpg', 'byte_size' => 10]);

    $export = app(RequestStudioExport::class)(StudioExportKind::Galerie, $gallery, $owner);

    expect(fn () => packedExport($export))->toThrow(RuntimeException::class)
        ->and($export->fresh()->status)->toBe(ZipBuildStatus::Failed);
});

it('starts one job per kind and gallery, however often it is asked', function () {
    $owner = staffMember(StaffRole::Admin);
    $gallery = Gallery::factory()->create();

    $this->actingAs($owner, 'staff')->post('/einstellungen/export/archiv')->assertRedirect();
    $this->actingAs($owner, 'staff')->post('/einstellungen/export/archiv')->assertRedirect();
    $this->actingAs($owner, 'staff')->post('/einstellungen/export/archiv', ['gallery' => $gallery->public_id])->assertRedirect();

    Queue::assertPushed(BuildStudioExport::class, 2);
    expect(StudioExport::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('event', 'studio_export.requested')->count())->toBe(2);
});

it('hides creating and loading archives from everybody but the owner', function () {
    $photographer = staffMember(StaffRole::Manager);
    $export = StudioExport::create(['kind' => StudioExportKind::Daten, 'status' => ZipBuildStatus::Ready, 'path' => 'exports/x.zip', 'expires_at' => now()->addDay()]);
    $url = URL::temporarySignedRoute('settings.export.archive.download', now()->addHour(), ['studioExport' => $export->public_id]);

    $this->actingAs($photographer, 'staff')->post('/einstellungen/export/archiv')->assertNotFound();
    $this->actingAs($photographer, 'staff')->get($url)->assertNotFound();
    Queue::assertNothingPushed();
});

it('sends the owner to a short-lived bucket link and audits the download', function () {
    $owner = staffMember(StaffRole::Admin);
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => "https://bucket.test/{$path}");
    $export = StudioExport::create(['kind' => StudioExportKind::Daten, 'status' => ZipBuildStatus::Ready, 'path' => 'exports/x.zip', 'expires_at' => now()->addDay()]);

    $this->actingAs($owner, 'staff');
    $props = props('/einstellungen/export');
    $url = $props['archives']['daten']['url'];

    $this->actingAs($owner, 'staff')->get($url)->assertRedirect('https://bucket.test/exports/x.zip');
    expect(AuditLog::query()->where('event', 'studio_export.downloaded')->where('subject', $export->public_id)->count())->toBe(1)
        ->and(integerIdPaths($props))->toBe([]);
});

it('serves nothing once the archive has expired', function () {
    $owner = staffMember(StaffRole::Admin);
    $export = StudioExport::create(['kind' => StudioExportKind::Daten, 'status' => ZipBuildStatus::Ready, 'path' => 'exports/x.zip', 'expires_at' => now()->subMinute()]);
    $url = URL::temporarySignedRoute('settings.export.archive.download', now()->addHour(), ['studioExport' => $export->public_id]);

    $this->actingAs($owner, 'staff')->get($url)->assertNotFound();

    $this->travel(2)->hours();
    $this->actingAs($owner, 'staff')->get($url)->assertForbidden();
});

it('deletes the archive with the expired row', function () {
    Storage::disk('s3')->put('exports/x.zip', 'zip');
    StudioExport::create(['kind' => StudioExportKind::Daten, 'status' => ZipBuildStatus::Ready, 'path' => 'exports/x.zip', 'expires_at' => now()->subMinute()]);

    $this->artisan('model:prune', ['--model' => [StudioExport::class]])->assertSuccessful();

    expect(StudioExport::query()->count())->toBe(0);
    Storage::disk('s3')->assertMissing('exports/x.zip');
});

it('accounts for every table with a tenant_id, in the export or on purpose not', function () {
    $tenantTables = collect(Schema::getTables())
        ->pluck('name')
        ->filter(fn (string $table) => Schema::hasColumn($table, 'tenant_id'))
        ->sort()->values()->all();

    $accounted = StudioExportTables::all();

    expect(array_values(array_diff($tenantTables, $accounted)))->toBe([], 'Tabelle fehlt in StudioExportTables')
        ->and(array_values(array_diff($accounted, $tenantTables)))->toBe([], 'StudioExportTables nennt eine Tabelle, die es nicht gibt')
        ->and(array_unique($accounted))->toEqual($accounted);
});
