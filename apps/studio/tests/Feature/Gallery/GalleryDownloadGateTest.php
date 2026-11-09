<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Gallery\Actions\RemindGalleryExpiry;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Notifications\GalleryExpiring;
use App\Gallery\Support\GalleryDownloadGate;
use App\Media\Enums\ZipBuildSize;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function invoiceOn(ClientCase $case, bool $paid): Invoice
{
    static $number = 0;

    $invoice = Invoice::query()->forceCreate(['number' => 'R-'.++$number, 'case_id' => $case->id, 'issued_at' => now()]);
    $plan = PaymentPlan::query()->firstOrCreate(['case_id' => $case->id], ['total' => 0]);
    $plan->update(['total' => $plan->total + 1000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 1000,
        'invoice_id' => $invoice->id,
        'status' => $paid ? PaymentInstallmentStatus::Paid : PaymentInstallmentStatus::Invoiced,
    ]);

    return $invoice;
}

it('opens downloads for the open rule and closes them for off', function () {
    $gallery = Gallery::factory()->create(['download_rule' => 'open']);
    expect(GalleryDownloadGate::allows($gallery))->toBeTrue();

    $gallery->update(['download_rule' => 'off']);
    expect(GalleryDownloadGate::allows($gallery))->toBeFalse()
        ->and($gallery->downloads_enabled)->toBeFalse();
});

it('keeps the legacy downloads_enabled column and the rule in step', function () {
    $gallery = Gallery::factory()->create(['download_rule' => 'after_payment']);
    expect($gallery->downloads_enabled)->toBeTrue();

    $gallery->update(['downloads_enabled' => false]);
    expect($gallery->fresh()->download_rule)->toBe('off');

    $gallery->update(['downloads_enabled' => true]);
    expect($gallery->fresh()->download_rule)->toBe('open');
});

it('holds an after_payment gallery shut until the chosen invoice is paid', function () {
    $gallery = Gallery::factory()->create(['download_rule' => 'after_payment']);
    $open = invoiceOn($gallery->case, paid: false);
    $paid = invoiceOn($gallery->case, paid: true);

    $gallery->update(['download_invoice_id' => $open->id]);
    expect(GalleryDownloadGate::allows($gallery->fresh()))->toBeFalse()
        ->and(GalleryDownloadGate::invoiceToPay($gallery->fresh())->is($open))->toBeTrue();

    $gallery->update(['download_invoice_id' => $paid->id]);
    expect(GalleryDownloadGate::allows($gallery->fresh()))->toBeTrue();
});

it('needs every invoice of the project paid when none is chosen, and at least one to exist', function () {
    $gallery = Gallery::factory()->create(['download_rule' => 'after_payment']);
    expect(GalleryDownloadGate::allows($gallery))->toBeFalse();

    invoiceOn($gallery->case, paid: true);
    expect(GalleryDownloadGate::allows($gallery->fresh()))->toBeTrue();

    $late = invoiceOn($gallery->case, paid: false);
    expect(GalleryDownloadGate::allows($gallery->fresh()))->toBeFalse();

    $late->forceFill(['cancelled_at' => now()])->save();
    expect(GalleryDownloadGate::allows($gallery->fresh()))->toBeTrue();
});

it('answers 403 on the guest download route while payment is outstanding', function () {
    useSurface(Surface::API);
    $gallery = Gallery::factory()->create(['download_rule' => 'after_payment']);
    GalleryVersion::factory()->for($gallery)->create();
    invoiceOn($gallery->case, paid: false);

    $this->getJson("/api/galleries/{$gallery->public_id}/download")->assertForbidden();

    Invoice::query()->where('case_id', $gallery->case_id)->get()
        ->each(fn (Invoice $invoice) => $invoice->installments()->update(['status' => PaymentInstallmentStatus::Paid]));

    $this->getJson("/api/galleries/{$gallery->public_id}/download")->assertOk();
});

it('offers only the sizes the gallery allows', function () {
    $gallery = Gallery::factory()->create(['download_sizes' => ['web']]);

    expect(GalleryDownloadGate::allowsSize($gallery, ZipBuildSize::Web))->toBeTrue()
        ->and(GalleryDownloadGate::allowsSize($gallery, ZipBuildSize::Original))->toBeFalse();
});

describe('expiry reminder', function () {
    function remindable(int $inDays, bool $reminder = true): Gallery
    {
        $gallery = Gallery::factory()->create(['expires_at' => now()->addDays($inDays), 'expiry_reminder' => $reminder]);
        GalleryVersion::factory()->for($gallery)->create();
        $gallery->case->attachContact(Contact::factory()->create(['email' => fake()->unique()->safeEmail()]), CaseContactRole::Primary);

        return $gallery;
    }

    it('mails the primary contact once inside the window', function () {
        Notification::fake();
        $gallery = remindable(10);

        expect(app(RemindGalleryExpiry::class)())->toBe(1)
            ->and(app(RemindGalleryExpiry::class)())->toBe(0);

        Notification::assertSentTimes(GalleryExpiring::class, 1);
        expect($gallery->fresh()->expiry_reminded_at)->not->toBeNull();
    });

    it('ignores a gallery outside the window or without the switch', function () {
        Notification::fake();
        remindable(40);
        remindable(5, reminder: false);

        expect(app(RemindGalleryExpiry::class)())->toBe(0);
        Notification::assertNothingSent();
    });

    it('reminds again after the expiry moves', function () {
        Notification::fake();
        $gallery = remindable(10);
        app(RemindGalleryExpiry::class)();

        $gallery->update(['expires_at' => now()->addDays(12)]);

        expect($gallery->fresh()->expiry_reminded_at)->toBeNull()
            ->and(app(RemindGalleryExpiry::class)())->toBe(1);
    });
});

it('carries a closed gallery over as download_rule off when the migration runs', function () {
    $migration = require database_path('migrations/2026_11_09_000300_add_gallery_view_mode_and_download_rule.php');
    $closed = Gallery::factory()->create(['downloads_enabled' => false]);
    $open = Gallery::factory()->create();

    $migration->down();
    DB::table('galleries')->where('id', $closed->id)->update(['downloads_enabled' => false]);
    $migration->up();

    $row = DB::table('galleries')->where('id', $closed->id)->first();
    expect($row->download_rule)->toBe('off')
        ->and($row->view_mode)->toBe('none')
        ->and(json_decode($row->download_sizes))->toBe(['original', 'web'])
        ->and(DB::table('galleries')->where('id', $open->id)->value('download_rule'))->toBe('open');
});

describe('admin write', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff'));

    it('saves view mode, rule, invoice of the own project and sizes', function () {
        $gallery = Gallery::factory()->create();
        $invoice = invoiceOn($gallery->case, paid: false);

        $this->patch("/galerien/{$gallery->public_id}", [
            'view_mode' => 'text',
            'watermark_text' => 'Studio Rahn',
            'download_rule' => 'after_payment',
            'download_invoice_id' => $invoice->public_id,
            'download_sizes' => ['web'],
        ])->assertRedirect();

        expect($gallery->fresh())
            ->view_mode->toBe('text')
            ->watermark_text->toBe('Studio Rahn')
            ->download_rule->toBe('after_payment')
            ->download_invoice_id->toBe($invoice->id)
            ->download_sizes->toBe(['web']);
    });

    it('refuses an invoice of another project and an empty size list', function () {
        $gallery = Gallery::factory()->create();
        $foreign = invoiceOn(Gallery::factory()->create()->case, paid: false);

        $this->patch("/galerien/{$gallery->public_id}", ['download_invoice_id' => $foreign->public_id])->assertSessionHasErrors('download_invoice_id');
        $this->patch("/galerien/{$gallery->public_id}", ['download_sizes' => []])->assertSessionHasErrors('download_sizes');
    });
});
