<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Gallery\Actions\ConsumeGalleryEmailVerification;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryListItem;
use App\Gallery\Notifications\GalleryEmailVerificationLink;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Jobs\BuildZip;
use App\Media\Models\Asset;
use App\Media\Models\ZipBuild;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
});

// ---------- the view gate (mockup 08) ----------

it('shows the gallery unlocked when no password is set', function () {
    $gallery = Gallery::factory()->create();

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['unlocked'])->toBeTrue();
});

it('asks for a password before unlocking a guarded gallery', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['unlocked'])->toBeFalse();
});

it('refuses the wrong password and grants no claim', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(props("/galerien/{$gallery->public_id}")['unlocked'])->toBeFalse();
});

it('unlocks on the right password and stays unlocked on the next request', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026'])
        ->assertRedirect("/galerien/{$gallery->public_id}");

    expect(props("/galerien/{$gallery->public_id}")['unlocked'])->toBeTrue();
});

it('does not carry a claim from one gallery to another', function () {
    $a = Gallery::factory()->create();
    $a->rotateViewPassword('one');
    $b = Gallery::factory()->create();
    $b->rotateViewPassword('two');

    $this->post("/galerien/{$a->public_id}/passwort", ['password' => 'one']);

    expect(props("/galerien/{$a->public_id}")['unlocked'])->toBeTrue()
        ->and(props("/galerien/{$b->public_id}")['unlocked'])->toBeFalse();
});

// ---------- G-14: withdrawal closes this entry point, not only the gated ones ----------

it('404s the gate screen for a withdrawn gallery, no password required', function () {
    $gallery = Gallery::factory()->create();
    Asset::factory()->for($gallery)->create(['key' => 'galleries/g/a.jpg']);
    $gallery->publish();
    $gallery->forceFill(['unpublished_at' => now()])->save();

    $this->get("/galerien/{$gallery->public_id}")->assertNotFound();

    expect($gallery->fresh()->versions()->count())->toBe(1);
});

// R17: store() is the uniform-failure path (unlike show(), which does 404
// a withdrawn gallery) — a distinct status here would let a garbage
// password enumerate withdrawn ids, exactly what G-07 closes for a
// nonexistent one.
it('answers a withdrawn gallery exactly as it answers one that never existed', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    $gallery->publish();
    $gallery->forceFill(['unpublished_at' => now()])->save();

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'irgendwas'])
        ->assertRedirect()
        ->assertSessionHasErrors(['password' => __('gallery.password_wrong')]);

    $this->post('/galerien/nonexistent-cuid/passwort', ['password' => 'irgendwas'])
        ->assertRedirect()
        ->assertSessionHasErrors(['password' => __('gallery.password_wrong')]);

    expect($gallery->fresh()->versions()->count())->toBe(1);
});

// ---------- reaching "download" requires the view gate first ----------

it('bounces an unauthenticated download request back to the password gate', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $this->get("/galerien/{$gallery->public_id}/download")
        ->assertRedirect("/galerien/{$gallery->public_id}");
});

/** Die Zustelladresse, die der Download seither immer verlangt. */
function leaveAddress(Gallery $gallery, string $email = 'gast@example.test'): void
{
    test()->post("/galerien/{$gallery->public_id}/download/email", ['email' => $email]);
}

it('reaches the download screen once the view gate is satisfied', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026']);

    // Eine Zustelladresse verlangt der Download immer — ohne sie hat das
    // fertig gepackte Archiv niemanden, dem es Bescheid sagt. Nur ob sie
    // bestätigt sein muss, entscheidet das Studio.
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('email');

    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test']);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');
});

// Verlangt das Studio keinen Nachweis, ist die Adresse kein Tor: kein Code,
// keine Mail, sofort weiter.
it('takes the address without a code when the studio asks for no proof', function () {
    $gallery = Gallery::factory()->create();

    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test']);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready')
        ->and(GalleryGuest::sole()->email)->toBe('anna@example.test');
});

// ---------- the balance gate (G-05) ----------

it('withholds the download while the studio ties it to an unpaid invoice', function () {
    $project = Project::factory()->create();
    $plan = PaymentPlan::factory()->for(Job::factory()->for($project)->create())->create();
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create();
    $gallery = Gallery::factory()->for($project)->create(['requires_settled_balance' => true]);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('balance');

    $installment->update(['status' => PaymentInstallmentStatus::Paid]);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('email');
});

it('leaves the download alone when the studio does not tie it to the invoice', function () {
    $project = Project::factory()->create();
    PaymentInstallment::factory()
        ->for(PaymentPlan::factory()->for(Job::factory()->for($project)->create())->create(), 'plan')
        ->create();
    $gallery = Gallery::factory()->for($project)->create(['requires_settled_balance' => false]);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('email');
});

// ---------- the download PIN gate (mockup 11) ----------

it('asks for the download pin before granting the download claim', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');

    $props = props("/galerien/{$gallery->public_id}/download");

    expect($props['step'])->toBe('pin');
});

it('refuses the wrong pin and grants no download claim', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');

    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '0000'])
        ->assertSessionHasErrors('pin');

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('pin');
});

it('a guest holding only the view password cannot reach a ready download', function () {
    // The story's own acceptance criterion: the view gate alone is not
    // enough. No password is set here at all — the point is that even an
    // entirely public gallery still enforces its download PIN independently.
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('pin');

    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);
    leaveAddress($gallery);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');
});

it('bounces an email request made before the pin gate is cleared', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    $gallery->rotateEmailVerification(true);

    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test'])
        ->assertRedirect("/galerien/{$gallery->public_id}/download");

    expect(GalleryEmailVerification::count())->toBe(0);
});

// ---------- the email gate (mockup 12) ----------

it('moves to the email step once the pin is cleared', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateDownloadPin('4821');
    $gallery->rotateEmailVerification(true);
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('email');
});

it('sends a verification link and grants the claim once it is opened', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateEmailVerification(true);

    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test'])
        ->assertRedirect();

    $verification = GalleryEmailVerification::sole();
    expect($verification->email)->toBe('anna@example.test')
        ->and($verification->consumed_at)->toBeNull();

    Notification::assertSentOnDemand(GalleryEmailVerificationLink::class);

    $token = null;
    Notification::assertSentOnDemand(
        GalleryEmailVerificationLink::class,
        function ($notification, $channels, $notifiable) use (&$token) {
            $token = (string) str($notification->toMail($notifiable)->actionUrl)->afterLast('/');

            return true;
        }
    );

    $this->get("/galerien/{$gallery->public_id}/download/email/{$token}")
        ->assertRedirect("/galerien/{$gallery->public_id}/download");

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready')
        ->and($verification->fresh()->consumed_at)->not->toBeNull();
});

it('refuses a forged email verification token', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateEmailVerification(true);

    $this->get('/galerien/'.$gallery->public_id.'/download/email/'.str_repeat('a', 64));

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('email');
});

it('lets one email link be spent only once', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateEmailVerification(true);
    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test']);
    $verification = GalleryEmailVerification::sole();

    $first = app(ConsumeGalleryEmailVerification::class);
    // The raw token never left the notification, so reissue one the same way
    // the action itself would have and hash it the same way to compare.
    $raw = 'known-for-the-test';
    $verification->forceFill(['token_hash' => GalleryEmailVerification::hash($raw)])->save();

    expect($first($gallery, $raw))->toBe('anna@example.test')
        ->and($first($gallery, $raw))->toBeNull();
});

// ---------- rotation independence — the story's central acceptance criterion ----------

it('rotating the pin leaves an existing view session and email verification intact', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    $gallery->rotateDownloadPin('4821');
    $gallery->rotateEmailVerification(true);

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026']);
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);
    $this->post("/galerien/{$gallery->public_id}/download/email", ['email' => 'anna@example.test']);
    $verification = GalleryEmailVerification::sole();
    $raw = 'known-for-the-test';
    $verification->forceFill(['token_hash' => GalleryEmailVerification::hash($raw)])->save();
    $this->get("/galerien/{$gallery->public_id}/download/email/{$raw}");

    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');

    $gallery->rotateDownloadPin('9999');

    // The view gate is untouched — no re-entering the password.
    expect(props("/galerien/{$gallery->public_id}")['unlocked'])->toBeTrue();

    // The download gate is back to asking, because its claim carried the old
    // version.
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('pin');

    // Clearing the new pin restores "ready" without ever re-asking for the
    // email — that claim's own version was never touched.
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '9999']);
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');
});

it('rotating the view password does not touch a granted download or email claim', function () {
    Notification::fake();
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');
    $gallery->rotateDownloadPin('4821');

    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'basthorst2026']);
    $this->post("/galerien/{$gallery->public_id}/download/pin", ['pin' => '4821']);
    leaveAddress($gallery);
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');

    $gallery->rotateViewPassword('newpassword');

    // The download claim survives even though the view claim is now stale —
    // but reaching /download at all requires the view gate, so this proves
    // the download version specifically was never bumped.
    $this->post("/galerien/{$gallery->public_id}/passwort", ['password' => 'newpassword']);
    expect(props("/galerien/{$gallery->public_id}/download")['step'])->toBe('ready');
});

// ---------- G-03 (#231): the download screen ----------

it('offers everything plus the client\'s own selections, each with both sizes', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $gallery = Gallery::factory()->for($project)->create();
    $a = Asset::factory()->for($gallery)->create([
        'key' => 'galleries/g/a.jpg', 'status' => AssetStatus::Ready, 'byte_size' => 4_000_000,
    ]);
    Asset::factory()->for($gallery)->create([
        'key' => 'galleries/g/b.jpg', 'status' => AssetStatus::Ready, 'byte_size' => 6_000_000,
    ]);
    $gallery->fresh()->publish();

    $list = GalleryList::create([
        'gallery_id' => $gallery->id, 'contact_id' => $contact->id, 'name' => 'Album',
    ]);
    GalleryListItem::create(['gallery_list_id' => $list->id, 'asset_key' => $a->key]);

    $props = test()->actingAs($contact, 'portal')
        ->get("/galerien/{$gallery->public_id}/download")
        ->assertOk()
        ->viewData('page')['props'];

    // name null is "everything"; the screen names it, not the controller.
    expect(collect($props['selections'])->pluck('name')->all())->toBe([null, 'Album'])
        ->and($props['selections'][0]['sizes']['original'])->toBe(10_000_000)
        ->and($props['selections'][1]['count'])->toBe(1)
        ->and($props['selections'][1]['sizes']['original'])->toBe(4_000_000);
});

// A named list belongs to the client — G-02 draws the same line in the grid.
it('offers a guest only everything, never somebody\'s named list', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $gallery = Gallery::factory()->for($project)->create();
    Asset::factory()->for($gallery)->create(['key' => 'galleries/g/a.jpg']);
    $gallery->publish();
    GalleryList::create([
        'gallery_id' => $gallery->id, 'contact_id' => $contact->id, 'name' => 'Album',
    ]);

    leaveAddress($gallery);

    $props = props("/galerien/{$gallery->public_id}/download");

    expect(collect($props['selections'])->pluck('name')->all())->toBe([null]);
});

it('queues one build per selection and size, and reuses a fresh one', function () {
    Queue::fake();

    $gallery = Gallery::factory()->create();
    Asset::factory()->for($gallery)->create(['key' => 'galleries/g/a.jpg']);
    $gallery->publish();
    leaveAddress($gallery);

    test()->post("/galerien/{$gallery->public_id}/download/vorbereiten", ['size' => 'original'])
        ->assertRedirect();

    $build = ZipBuild::sole();
    expect($build->scope)->toBe(ZipBuildScope::All)
        ->and($build->status)->toBe(ZipBuildStatus::Building);

    Queue::assertPushed(BuildZip::class, 1);

    // A different size is a different archive, not the same one again.
    test()->post("/galerien/{$gallery->public_id}/download/vorbereiten", ['size' => 'web']);
    expect(ZipBuild::count())->toBe(2);

    // A Ready one within the window is not rebuilt.
    $build->update(['status' => ZipBuildStatus::Ready]);
    test()->post("/galerien/{$gallery->public_id}/download/vorbereiten", ['size' => 'original']);

    Queue::assertPushed(BuildZip::class, 2);
});

it('refuses to prepare a list the visitor does not own', function () {
    Queue::fake();

    $gallery = Gallery::factory()->create();
    Asset::factory()->for($gallery)->create(['key' => 'galleries/g/a.jpg']);
    $gallery->publish();
    $list = GalleryList::create([
        'gallery_id' => $gallery->id,
        'contact_id' => Contact::factory()->create()->id,
        'name' => 'Album',
    ]);

    test()->post("/galerien/{$gallery->public_id}/download/vorbereiten", [
        'size' => 'original', 'list' => $list->public_id,
    ])->assertNotFound();

    expect(ZipBuild::count())->toBe(0);
});

// One button would be lying about 33 originals at 4,2 GB.
it('hands back every part of a ready build with its own signed link', function () {
    $gallery = Gallery::factory()->create();
    Asset::factory()->for($gallery)->create(['key' => 'galleries/g/a.jpg']);
    $version = $gallery->publish();

    $build = ZipBuild::create([
        'gallery_version_id' => $version->id,
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Original,
        'status' => ZipBuildStatus::Ready,
    ]);
    $build->parts()->createMany([
        ['part_number' => 1, 'key' => 'zips/part-1.zip', 'byte_size' => 1_500_000_000],
        ['part_number' => 2, 'key' => 'zips/part-2.zip', 'byte_size' => 900_000_000],
    ]);
    leaveAddress($gallery);

    $props = props("/galerien/{$gallery->public_id}/download");
    $row = collect($props['builds'])->sole();

    expect($row['partCount'])->toBe(2)
        ->and($row['parts'][0]['bytes'])->toBe(1_500_000_000)
        ->and($row['parts'][0]['url'])->toContain('signature=')
        ->and($row['parts'][1]['number'])->toBe(2);
});

// A guest cleared the view gate, so the photographs are theirs to see. The
// couple's curation is not: "Für die Eltern" is their words, and a
// list-scoped archive is their selection, not a stranger's to download.
it('never shows a guest the client\'s own archives or what they called them', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $gallery = Gallery::factory()->for($project)->create();
    Asset::factory()->for($gallery)->create(['key' => 'galleries/g/a.jpg']);
    $version = $gallery->publish();

    $list = GalleryList::create([
        'gallery_id' => $gallery->id, 'contact_id' => $contact->id, 'name' => 'Für die Eltern',
    ]);

    foreach ([null, $list->id] as $listId) {
        ZipBuild::create([
            'gallery_version_id' => $version->id,
            'scope' => $listId === null ? ZipBuildScope::All : ZipBuildScope::ListSelection,
            'size' => ZipBuildSize::Original,
            'gallery_list_id' => $listId,
            'status' => ZipBuildStatus::Ready,
        ]);
    }

    leaveAddress($gallery);
    $asGuest = props("/galerien/{$gallery->public_id}/download");

    expect(collect($asGuest['builds'])->pluck('list')->all())->toBe([''])
        ->and(json_encode($asGuest))->not->toContain('Für die Eltern');

    $asClient = test()->actingAs($contact, 'portal')
        ->get("/galerien/{$gallery->public_id}/download")
        ->assertOk()->viewData('page')['props'];

    expect(collect($asClient['builds'])->pluck('listName')->all())
        ->toEqualCanonicalizing([null, 'Für die Eltern']);
});
