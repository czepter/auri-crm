<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Gallery\Actions\BuildGalleryZip;
use App\Gallery\Actions\SetAssetHidden;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\AssetHide;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\Asset;
use App\Media\Models\ZipBuild;
use App\Support\Surface;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const OPEN = 'galleries/g/open.jpg';
const PRIVATE_KEY = 'galleries/g/private.jpg';

/** Ein Set für Gäste, eines nur für das Projekt; beide veröffentlicht. */
function audienceGallery(array $attributes = []): Gallery
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $gallery = Gallery::factory()->for($project, 'case')->create($attributes);
    $open = $gallery->sets()->first();
    $open->update(['name' => 'Feier', 'access' => GallerySet::GUESTS]);
    $private = GallerySet::factory()->create(['gallery_id' => $gallery->id, 'name' => 'Familie', 'position' => 1, 'access' => GallerySet::INTERNAL]);

    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $open->id, 'key' => OPEN, 'filename' => 'open.jpg']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $private->id, 'key' => PRIVATE_KEY, 'filename' => 'private.jpg']);
    $gallery->fresh()->publish();

    return $gallery->fresh();
}

function clientOf(Gallery $gallery, CaseContactRole $role = CaseContactRole::Primary): Contact
{
    $contact = Contact::factory()->create();
    $gallery->case->attachContact($contact, $role);

    return $contact;
}

describe('portal', function () {
    beforeEach(fn () => useSurface(Surface::PORTAL));

    it('refuses a guest a heart, a comment or hiding when the matrix says no, and a client what is off for clients', function () {
        $gallery = audienceGallery(['permissions' => [
            'guest' => ['favorites' => false],
            'client' => ['comments' => false],
        ]]);
        $contact = clientOf($gallery);

        test()->postJson("/galerien/{$gallery->public_id}/favorit", ['key' => OPEN])->assertForbidden();
        test()->postJson("/galerien/{$gallery->public_id}/kommentar", ['key' => OPEN, 'body' => 'Hallo'])->assertForbidden();
        test()->postJson("/galerien/{$gallery->public_id}/ausblenden", ['key' => OPEN])->assertForbidden();

        test()->actingAs($contact, 'portal');
        test()->postJson("/galerien/{$gallery->public_id}/favorit", ['key' => OPEN])->assertOk();
        test()->postJson("/galerien/{$gallery->public_id}/kommentar", ['key' => OPEN, 'body' => 'Hallo'])->assertForbidden();

        expect(AssetFavorite::count())->toBe(1)->and(AssetComment::count())->toBe(0);
    });

    it('lets a client hide and unhide, and takes the picture off their wall', function () {
        $gallery = audienceGallery();
        $contact = clientOf($gallery);
        test()->actingAs($contact, 'portal');

        test()->postJson("/galerien/{$gallery->public_id}/ausblenden", ['key' => OPEN])->assertOk()->assertJson(['hidden' => true]);
        expect(AssetHide::where('contact_id', $contact->id)->count())->toBe(1);

        test()->get("/galerien/{$gallery->public_id}")->assertInertia(fn ($page) => $page
            ->has('assets', 1)
            ->where('assets.0.key', PRIVATE_KEY)
            ->where('hiddenAssets.0.key', OPEN));

        test()->postJson("/galerien/{$gallery->public_id}/ausblenden", ['key' => OPEN])->assertOk()->assertJson(['hidden' => false]);
        expect(AssetHide::count())->toBe(0);
    });

    it('keeps one client\'s hidden pictures on their own wall only', function () {
        $gallery = audienceGallery();
        $first = clientOf($gallery);
        $second = clientOf($gallery, CaseContactRole::Partner);
        app(SetAssetHidden::class)($gallery, $first, OPEN, true);

        test()->actingAs($second, 'portal')->get("/galerien/{$gallery->public_id}")
            ->assertInertia(fn ($page) => $page->has('assets', 2)->where('hiddenAssets', []));
    });

    it('shows a guest only the guest sets, and the client both', function () {
        $gallery = audienceGallery();

        test()->get("/galerien/{$gallery->public_id}")
            ->assertInertia(fn ($page) => $page->has('assets', 1)->where('assets.0.key', OPEN)->where('offers.hide', false));

        test()->actingAs(clientOf($gallery), 'portal')->get("/galerien/{$gallery->public_id}")
            ->assertInertia(fn ($page) => $page->has('assets', 2)->where('offers.hide', true));
    });

    it('answers a set link for a set guests may not see with 404', function () {
        $gallery = audienceGallery();
        $private = $gallery->sets()->where('name', 'Familie')->first();
        $open = $gallery->sets()->where('name', 'Feier')->first();

        test()->get("/galerien/{$gallery->public_id}?set={$private->public_id}")->assertNotFound();
        test()->get("/galerien/{$gallery->public_id}?set={$open->public_id}")
            ->assertInertia(fn ($page) => $page->has('assets', 1)->where('setName', 'Feier'));
        test()->actingAs(clientOf($gallery), 'portal')->get("/galerien/{$gallery->public_id}?set={$private->public_id}")
            ->assertInertia(fn ($page) => $page->has('assets', 1)->where('assets.0.key', PRIVATE_KEY));
    });

    it('refuses the bytes of an internal set to a guest', function () {
        $gallery = audienceGallery();
        $version = $gallery->currentVersion;

        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagejpeg($image);
        Storage::fake('s3');
        Storage::disk('s3')->put(OPEN, ob_get_clean());

        test()->get("/galerie-bilder/{$version->public_id}/".OPEN.'?w=400&fmt=jpeg')->assertSuccessful();
        test()->get("/galerie-bilder/{$version->public_id}/".PRIVATE_KEY.'?w=400&fmt=jpeg')->assertNotFound();
    });

    it('does not let a guest mark a picture from an internal set', function () {
        $gallery = audienceGallery();

        test()->postJson("/galerien/{$gallery->public_id}/favorit", ['key' => PRIVATE_KEY])->assertNotFound();
    });

    it('passes the intro switch and the share link to the screen', function () {
        $gallery = audienceGallery(['intro_enabled' => true]);
        $open = $gallery->sets()->where('name', 'Feier')->first();

        test()->get("/galerien/{$gallery->public_id}?set={$open->public_id}")
            ->assertInertia(fn ($page) => $page->where('intro', true)->where('shareUrl', $gallery->publicUrl().'?set='.$open->public_id));
    });
});

describe('api', function () {
    beforeEach(fn () => useSurface(Surface::API));

    it('serves a guest the guest sets only, with the permissions and the intro flag', function () {
        $gallery = audienceGallery(['intro_enabled' => true]);

        $this->getJson("/api/galleries/{$gallery->public_id}")
            ->assertOk()
            ->assertJsonCount(1, 'gallery.version.assets')
            ->assertJsonPath('gallery.version.assets.0.key', OPEN)
            ->assertJsonPath('gallery.permissions', ['favorites' => true, 'comments' => false, 'hide' => false, 'share' => true])
            ->assertJsonPath('gallery.intro', true)
            ->assertJsonCount(1, 'gallery.sets');
    });

    it('refuses an internal image, a heart and a comment to a guest', function () {
        $gallery = audienceGallery(['permissions' => ['guest' => ['comments' => true]]]);
        $version = $gallery->currentVersion;
        [, $pass] = confirmedGuest($gallery);

        $this->get("/api/gallery-versions/{$version->public_id}/images/".PRIVATE_KEY.'?w=400&fmt=jpeg')->assertNotFound();
        $this->postJson("/api/gallery-versions/{$version->public_id}/favorites/".PRIVATE_KEY, ['guest_pass' => $pass])->assertNotFound();
        $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
            'guest_pass' => $pass, 'asset_key' => PRIVATE_KEY, 'body' => 'x',
        ])->assertNotFound();
    });

    it('does not let a guest comment or mark when the studio switched it off', function () {
        $gallery = audienceGallery(['permissions' => ['guest' => ['favorites' => false]]]);
        $version = $gallery->currentVersion;
        [, $pass] = confirmedGuest($gallery);

        $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
            'guest_pass' => $pass, 'asset_key' => OPEN, 'body' => 'x',
        ])->assertForbidden();
        $this->postJson("/api/gallery-versions/{$version->public_id}/favorites/".OPEN, ['guest_pass' => $pass])->assertForbidden();
    });
});

describe('archives', function () {
    beforeEach(fn () => Storage::fake('s3'));

    it('packs the internal set for the client and leaves it out of a guest\'s archive', function () {
        $gallery = audienceGallery();
        $version = $gallery->currentVersion;

        foreach (Asset::where('gallery_id', $gallery->id)->get() as $asset) {
            $asset->update(['status' => AssetStatus::Ready, 'byte_size' => 3, 'key' => "galleries/{$gallery->public_id}/{$asset->filename}"]);
            Storage::disk('s3')->put($asset->key, 'abc');
        }
        $version->update(['assets' => collect($version->assets)->map(fn ($a) => [...$a, 'key' => "galleries/{$gallery->public_id}/".basename($a['key'])])->all()]);

        $captured = [];
        $aborted = [];
        app()->instance(S3Client::class, zipMultipartMock($captured, $aborted));

        $names = [];
        foreach ([true, false] as $guests) {
            $build = ZipBuild::factory()->for($version->fresh(), 'galleryVersion')->create([
                'scope' => ZipBuildScope::All, 'size' => ZipBuildSize::Original, 'status' => ZipBuildStatus::Building, 'guests' => $guests,
            ]);
            $captured = [];
            app(BuildGalleryZip::class)($build);
            $zip = assembledUpload($captured, array_key_first($captured));
            $names[$guests ? 'guest' : 'client'] = [str_contains($zip, 'open.jpg'), str_contains($zip, 'private.jpg')];
        }

        expect($names['client'])->toBe([true, true])->and($names['guest'])->toBe([true, false]);
    });
});
