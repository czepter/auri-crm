<?php

use App\Gallery\Actions\NotifyZipWaiters;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Notifications\GalleryZipReady;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\Asset;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildWaiter;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Notification::fake();

    $this->gallery = Gallery::factory()->create();
    Asset::factory()->for($this->gallery)->create(['key' => 'galleries/g/a.jpg']);
    $this->version = $this->gallery->publish();

    $this->build = ZipBuild::create([
        'gallery_version_id' => $this->version->id,
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Original,
        'status' => ZipBuildStatus::Ready,
    ]);
    $this->build->parts()->create(['part_number' => 1, 'key' => 'zips/p1.zip', 'byte_size' => 100]);
});

function waits(Gallery $gallery, ZipBuild $build, string $email): GalleryGuest
{
    $guest = GalleryGuest::forGallery($gallery, $email);
    ZipBuildWaiter::create(['zip_build_id' => $build->id, 'gallery_guest_id' => $guest->id]);

    return $guest;
}

// Ein Archiv über 14 GB wird im Hintergrund gepackt — niemand sitzt so lange
// vor der Seite. Ohne diese Mail wäre die Adressabfrage eine Erhebung ohne
// Zweck.
it('writes to everyone waiting on the archive', function () {
    waits($this->gallery, $this->build, 'anna@example.test');
    waits($this->gallery, $this->build, 'ben@example.test');

    app(NotifyZipWaiters::class)($this->build);

    Notification::assertSentOnDemandTimes(GalleryZipReady::class, 2);
});

// Ein Bau, der nach einem Fehlschlag erneut bis Ready läuft, darf niemanden
// ein zweites Mal anschreiben.
it('writes once, even when the build reaches Ready twice', function () {
    waits($this->gallery, $this->build, 'anna@example.test');

    app(NotifyZipWaiters::class)($this->build);
    app(NotifyZipWaiters::class)($this->build);

    Notification::assertSentOnDemandTimes(GalleryZipReady::class, 1);
    expect(ZipBuildWaiter::sole()->notified_at)->not->toBeNull();
});

// Der Bau gehört niemandem: zwei Gäste, die dasselbe anfordern, teilen sich
// die Bände. Wer auf ein anderes Archiv wartet, bekommt nichts.
it('leaves the waiters of another archive alone', function () {
    $other = ZipBuild::create([
        'gallery_version_id' => $this->version->id,
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Web,
        'status' => ZipBuildStatus::Building,
    ]);

    waits($this->gallery, $other, 'ben@example.test');

    app(NotifyZipWaiters::class)($this->build);

    Notification::assertNothingSent();
});

// Entscheidung 9: die Adresse ist der Grund, aus dem der Download nach einer
// fragt. Der ganze Weg in einem Test — Formular, Wartezeile, Mail. Die Queue
// läuft hier synchron, der Bau ist also schon durch, wenn die Antwort kommt.
it('carries the address from the download screen through to the mail', function () {
    $this->post("/galerien/{$this->gallery->public_id}/download/email", ['email' => 'anna@example.test']);
    $this->post("/galerien/{$this->gallery->public_id}/download/vorbereiten", ['size' => 'web']);

    expect(ZipBuildWaiter::sole()->guest->email)->toBe('anna@example.test');

    Notification::assertSentOnDemand(
        GalleryZipReady::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'anna@example.test',
    );
});

// Ein Gast-Bau führt auf die Galerie-Adresse, ein Portal-Bau ins Portal.
it('links a guest archive to the gallery address and a portal archive to the portal', function () {
    waits($this->gallery, $this->build, 'anna@example.test');

    app(NotifyZipWaiters::class)($this->build);

    $this->build->forceFill(['guests' => true])->save();
    ZipBuildWaiter::query()->update(['notified_at' => null]);

    app(NotifyZipWaiters::class)($this->build->fresh());

    expect(GalleryDownloadLink::query()->whereNull('zip_build_id')->count())->toBe(1)
        ->and(GalleryDownloadLink::query()->where('zip_build_id', $this->build->id)->count())->toBe(1);
});
