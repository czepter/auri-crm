<?php

use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\Task;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Media\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Studio\StudioDeletionCode;
use App\Notifications\Studio\StudioDeletionReminder;
use App\Payments\Actions\IssueCreditNote;
use App\Quotes\Models\Quote;
use App\Settings\Actions\DeleteTenant;
use App\Settings\Enums\StudioExportKind;
use App\Settings\Models\StudioExport;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const CODE_PATH = '/einstellungen/studio-loeschen';

beforeEach(function () {
    Notification::fake();
    Storage::fake('s3');
});

/** @return list<string> */
function tenantTables(): array
{
    return collect(Schema::getTables())->pluck('name')
        ->filter(fn (string $table) => Schema::hasColumn($table, 'tenant_id'))
        ->values()->all();
}

/** The code the owner was mailed. */
function mailedCode(User $owner): string
{
    $code = null;

    Notification::assertSentTo($owner, StudioDeletionCode::class, function (StudioDeletionCode $mail) use (&$code) {
        $code = (fn () => $this->code)->call($mail);

        return true;
    });

    return $code;
}

function requestDeletionAt(string $when): void
{
    StudioSettings::query()->update(['deletion_requested_at' => $when]);
    Cache::store('redis')->flush();
}

it('leaves no row in any table with a tenant_id', function () {
    Queue::fake();
    app(StudioSettingsResolver::class)->set('tax_number', '22/123/45678', null);
    $owner = staffMember(StaffRole::Owner);
    $installment = creditableInstallment();
    $project = $installment->invoice->project;
    app(IssueCreditNote::class)($installment->invoice, 25_000, 'Nachlass');

    $first = Quote::factory()->for($project)->create();
    Quote::factory()->for($project)->create(['predecessor_id' => $first->id]);
    Message::factory()->for(Thread::factory()->for($project))->create();
    Task::factory()->create();
    renderedContract($project);
    Asset::factory()->for(Gallery::factory())->create();
    StudioExport::create(['kind' => StudioExportKind::Daten, 'status' => 'ready']);
    AuditLogger::record('contact.created', $owner);

    $filled = collect(tenantTables())->filter(fn (string $table) => DB::table($table)->exists());
    expect($filled->count())->toBeGreaterThan(20);

    app(DeleteTenant::class)(config('tenancy.tenant_id'), 'test');

    // Bleibt: die leere Einstellungszeile, ohne die keine Seite rendert, und der Vermerk.
    $left = collect(tenantTables())->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->filter();

    expect($left->all())->toBe(['audit_logs' => 1, 'studio_settings' => 1])
        ->and(AuditLog::query()->sole()->event)->toBe('studio.deleted')
        ->and(AuditLog::query()->sole()->context)->toBe(['trigger' => 'test'])
        ->and(StudioSettings::query()->sole()->display_name)->toBeNull()
        ->and(User::query()->exists())->toBeFalse();
});

it('removes the files of the studio from both disks and only those', function () {
    Storage::fake('local');
    $tenant = config('tenancy.tenant_id');

    foreach (["invoices/{$tenant}/a.pdf", "branding/{$tenant}/logo.png", 'form-instance-files/abc/1.pdf', 'invoices/other/b.pdf'] as $path) {
        Storage::disk('local')->put($path, 'x');
    }
    foreach (["galleries/{$tenant}/g/original/1.jpg", "exports/{$tenant}/e.zip", 'galleries/other/1.jpg'] as $path) {
        Storage::disk('s3')->put($path, 'x');
    }

    FormInstanceField::factory()->for(FormInstance::factory())->create()->files()->create(['path' => 'form-instance-files/abc/1.pdf', 'name' => '1.pdf', 'mime' => 'application/pdf', 'size' => 1]);

    app(DeleteTenant::class)(config('tenancy.tenant_id'), 'test');

    expect(Storage::disk('local')->allFiles())->toBe(['invoices/other/b.pdf'])
        ->and(Storage::disk('s3')->allFiles())->toBe(['galleries/other/1.jpg']);
});

it('keeps the studio until the day, then deletes it and reminds the day before', function () {
    $owner = staffMember(StaffRole::Owner);
    requestDeletionAt(now()->toDateTimeString());
    $day = app(StudioSettingsResolver::class)->deletionDate();

    $this->travelTo($day->subDays(2)->setTime(3, 0));
    $this->artisan('studio:delete-due')->assertSuccessful();
    Notification::assertNothingSent();
    expect(User::query()->count())->toBe(1);

    $this->travelTo($day->subDay()->setTime(3, 0));
    $this->artisan('studio:delete-due')->assertSuccessful();
    Notification::assertSentToTimes($owner, StudioDeletionReminder::class, 1);
    expect(User::query()->count())->toBe(1);

    $this->travelTo($day->setTime(3, 0));
    $this->artisan('studio:delete-due')->assertSuccessful();
    expect(User::query()->count())->toBe(0)
        ->and(AuditLog::query()->sole()->event)->toBe('studio.deleted')
        ->and(AuditLog::query()->sole()->context)->toBe(['trigger' => 'deletion_requested']);
});

it('does nothing without a deletion request', function () {
    staffMember(StaffRole::Owner);

    $this->artisan('studio:delete-due')->assertSuccessful();

    expect(User::query()->count())->toBe(1);
});

it('withdraws the request on cancel', function () {
    $owner = staffMember(StaffRole::Owner);
    requestDeletionAt(now()->toDateTimeString());

    $this->actingAs($owner, 'staff')->delete(CODE_PATH)->assertRedirect();

    expect(StudioSettings::query()->sole()->deletion_requested_at)->toBeNull()
        ->and(app(StudioSettingsResolver::class)->deletionDate())->toBeNull();

    $this->travelTo(now()->addDays(40));
    $this->artisan('studio:delete-due')->assertSuccessful();
    expect(User::query()->count())->toBe(1);
});

it('hides the page and every action from everybody but the owner', function () {
    $photographer = staffMember(StaffRole::Photographer);

    $this->actingAs($photographer, 'staff')->get(CODE_PATH)->assertNotFound();
    $this->actingAs($photographer, 'staff')->post(CODE_PATH.'/code')->assertNotFound();
    $this->actingAs($photographer, 'staff')->post(CODE_PATH, ['code' => '123456'])->assertNotFound();
    $this->actingAs($photographer, 'staff')->delete(CODE_PATH)->assertNotFound();

    Notification::assertNothingSent();
    expect(StudioSettings::query()->sole()->deletion_requested_at)->toBeNull();
});

it('marks the studio only for the code that was mailed, once', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->post(CODE_PATH, ['code' => '123456'])->assertSessionHasErrors('code');

    $this->post(CODE_PATH.'/code')->assertRedirect();
    $code = mailedCode($owner);
    expect($code)->toMatch('/^\d{6}$/');

    $wrong = $code === '000000' ? '000001' : '000000';
    $this->post(CODE_PATH, ['code' => $wrong])->assertSessionHasErrors('code');
    expect(StudioSettings::query()->sole()->deletion_requested_at)->toBeNull();

    $this->post(CODE_PATH, ['code' => $code])->assertSessionHasNoErrors();
    expect(StudioSettings::query()->sole()->deletion_requested_at)->not->toBeNull()
        ->and(props('/')['studioDeletion'])->toBe(app(StudioSettingsResolver::class)->deletionDate()->format('d.m.Y'));

    $this->delete(CODE_PATH);
    $this->post(CODE_PATH, ['code' => $code])->assertSessionHasErrors('code');
    expect(StudioSettings::query()->sole()->deletion_requested_at)->toBeNull();
});

it('rejects an expired code and a replaced one', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->post(CODE_PATH.'/code');
    $first = mailedCode($owner);
    $this->travel(16)->minutes();
    $this->post(CODE_PATH, ['code' => $first])->assertSessionHasErrors('code');

    Notification::fake();
    $this->post(CODE_PATH.'/code');
    $old = mailedCode($owner);
    Notification::fake();
    $this->post(CODE_PATH.'/code');
    $new = mailedCode($owner);

    $this->post(CODE_PATH, ['code' => $old])->assertSessionHasErrors('code');
    expect(StudioSettings::query()->sole()->deletion_requested_at)->toBeNull();

    $this->post(CODE_PATH, ['code' => $new])->assertSessionHasNoErrors();
    expect(StudioSettings::query()->sole()->deletion_requested_at)->not->toBeNull();
});

it('locks out after five wrong codes, even for the right one', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->post(CODE_PATH.'/code');
    $code = mailedCode($owner);

    foreach (range(1, 5) as $ignored) {
        $this->post(CODE_PATH, ['code' => $code === '000000' ? '000001' : '000000'])->assertSessionHasErrors('code');
    }

    $this->post(CODE_PATH, ['code' => $code])->assertSessionHasErrors('code');
    expect(StudioSettings::query()->sole()->deletion_requested_at)->toBeNull();
});

it('names the code and the day in the mails', function () {
    $owner = staffMember(StaffRole::Owner);

    expect((string) (new StudioDeletionCode($owner->name, '482913'))->toMail($owner)->render())->toContain('482913')->toContain('30 Tagen')
        ->and((string) (new StudioDeletionReminder($owner->name, now()->setDate(2026, 10, 21)))->toMail($owner)->render())->toContain('21.10.2026');
});
