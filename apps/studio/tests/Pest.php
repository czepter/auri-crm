<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Documents\Models\DocumentTemplate;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Support\GalleryGuestPass;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Models\MultipartUpload;
use App\Models\User;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\Surface;
use Aws\MockHandler;
use Aws\S3\S3Client;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    // No feature test asserts a built asset — they assert the Inertia component
    // and the root view. Resolving the real manifest would make the PHP suite
    // depend on `pnpm build` having run first. The manifest stays proven by the
    // `web` job and by Playwright, which run against a real build.
    ->beforeEach(function () {
        $this->withoutVite();

        // Every feature test exercises a studio that has already finished
        // onboarding — RequireStudioOnboarding would otherwise redirect any
        // authenticated GET in this directory to /einrichtung/studio.
        // tests/Feature/Settings/OnboardingTest.php resets this per test to
        // exercise the unfinished state on purpose.
        //
        // StudioSettingsResolver reads through the `redis` store by name (see
        // its own doc comment) and only busts that cache from its own set()/
        // setLogo() — a raw query()->update() like this one does not, and
        // Redis outlives any one test process, so a value cached by an
        // earlier run would otherwise leak into this one. Flushing here is
        // the same fix StudioSettingsAdminTest's own beforeEach already
        // applies per-file; this makes it universal.
        if (Schema::hasTable('studio_settings')) {
            StudioSettings::query()->update(['onboarding_completed_at' => now()]);
            Cache::store('redis')->flush();
        }
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Walks a decoded response body and returns the paths of any key that looks
 * like an identifier but holds an integer. Passing a model straight into
 * Inertia::render instead of wrapping it in a Data object is the ordinary way
 * the internal key escapes, and it should fail here rather than in review.
 *
 * Lives in Pest.php rather than in a test file: three suites use it now, and a
 * global function declared inside one test file is only visible to another if
 * Pest happens to load them in the right order.
 */
/**
 * The Inertia page props of a GET, for the suites that assert what a screen was
 * handed rather than how it renders.
 */
function props(string $path): array
{
    return test()->get($path)->assertOk()->viewData('page')['props'];
}

/**
 * Switches the running application onto another surface, for the suites that
 * exercise one the test process did not boot as.
 *
 * Routes are registered at boot, so setting the config alone is too late — the
 * admin's route table is already in place. This puts the whole seam across:
 * the route file, the default guard and the session cookie, which is what
 * makes a request behave as it would in that process.
 */
function useSurface(string $surface): void
{
    config([
        'surface.name' => $surface,
        'session.cookie' => Surface::COOKIES[$surface],
    ]);

    // Route::setRoutes() itself already keeps route()/URL:: working: it
    // updates the container's 'routes' binding, and RoutingServiceProvider
    // wires a 'routes'-rebinding listener (see its registerUrlGenerator())
    // that calls the *existing* UrlGenerator's setRoutes() the moment that
    // happens — safe, because 'url' is never forgotten here, only updated
    // in place. (An earlier version of this helper called
    // app()->forgetInstance('url') to work around what turned out to be a
    // different problem below — that forced RoutingServiceProvider's
    // extend() step to re-run and register a second, third, ... "routes"
    // rebinding listener on every surface switch, and each one calling
    // $app['url'] while 'url' was mid-(re)build recursed without end. A
    // test that crosses surfaces more than once — Portal\RevocationTest's
    // admin round-trip — hit a real stack overflow from it.)
    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();

    // Route::name() only sets the route's own action['as'] — the
    // collection's name lookup table is a separate index, normally built
    // once by the framework's own boot-time pass over $file (Illuminate\
    // Foundation\Configuration\ApplicationBuilder's routing bootstrapper)
    // after every route in it is registered. Re-running Surface::
    // registerRoutes() here happens after that pass already ran once, so
    // nothing rebuilds the index for this surface's routes without this
    // call — route()/URL:: would otherwise throw RouteNotFoundException
    // even though the route is genuinely there (Route::has() and getByName()
    // read the index; iterating the collection and calling getName() on
    // each route does not).
    Route::getRoutes()->refreshNameLookups();

    Auth::shouldUse(match ($surface) {
        Surface::PORTAL => 'portal',
        Surface::API => 'api',
        default => 'staff',
    });
}

/**
 * Registers one surface's routes in isolation and returns them. Two suites
 * need it — the audience matrix and the internal-note reach test — which is
 * exactly the case the note above says belongs here rather than in whichever
 * test file happened to declare it first.
 */
function routesForSurface(string $surface): array
{
    config(['surface.name' => $surface]);

    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();

    return collect(Route::getRoutes()->getRoutes())->all();
}

/**
 * A staff user, optionally holding a specific role. Lives here rather than in
 * whichever test file happened to declare it first — Catalog and Queue both
 * need one now, and a global function declared inside one test file collides
 * the moment a second file declares the same name.
 */
function staffMember(?StaffRole $role = null): User
{
    return User::factory()->withMfa()->create($role ? ['role' => $role] : []);
}

/**
 * A Sanctum plain-text token for anything HasApiTokens — an ApiToken (I-01)
 * or a Contact's own guest token (G-01). Several api-surface suites need one
 * now, and a global function declared inside one test file collides the
 * moment a second file declares the same name — see useSurface()'s own note
 * just above for why this lives here rather than there.
 */
function bearerToken(mixed $tokenable): string
{
    return $tokenable->createToken('t')->plainTextToken;
}

/**
 * A booked project with a primary contact — the fixture RenderContractJob's
 * merge fields resolve against (project.name, contact.name, job.bookedAt).
 * Lives here rather than in whichever Contracts test file happened to
 * declare it first: several do now, and a global function declared inside
 * one test file collides the moment a second file declares the same name.
 */
function contractProject(): Project
{
    $project = Project::factory()->create(['name' => 'Hochzeit Meier & Kowalski']);
    $contact = Contact::factory()->create(['name' => 'Lena Meier', 'email' => 'lena@example.test']);
    $project->attachContact($contact, ProjectContactRole::Primary);
    Job::factory()->for($project)->create(['booked_at' => '2026-06-14 00:00:00']);

    return $project;
}

/**
 * A Contract already in the `rendered` state, with real bytes sitting on the
 * faked `local` disk at its artifact_path — call Storage::fake('local')
 * before this. Several Contracts suites need "a rendered contract that
 * exists" without caring how it got there.
 */
function renderedContract(?Project $project = null, string $bytes = '%PDF-stored-bytes'): Contract
{
    $contract = Contract::factory()
        ->for($project ?? Project::factory())
        ->for(DocumentTemplate::factory(), 'template')
        ->create(['status' => ContractStatus::Pending]);

    $path = "contracts/test/{$contract->public_id}.pdf";
    Storage::disk('local')->put($path, $bytes);
    $contract->update(['status' => ContractStatus::Rendered, 'artifact_path' => $path, 'rendered_at' => now()]);

    return $contract->fresh();
}

/**
 * An Aws\S3\S3Client wired to Aws\MockHandler instead of a live bucket — the
 * boundary ReapIncompleteMultipartUploadsTest's own comment flags as having
 * "no fake for that boundary exists in this repo yet" at the time G-01a
 * shipped. G-02's MultipartUploadGateway is the first thing to actually call
 * the S3 SDK's multipart operations, so this is that fake: bind the return
 * value with `$this->app->instance(S3Client::class, ...)` before hitting a
 * route that resolves MultipartUploadGateway.
 */
function mockS3Client(MockHandler $handler): S3Client
{
    return new S3Client([
        'version' => 'latest',
        'region' => 'eu-central-1',
        'credentials' => ['key' => 'test', 'secret' => 'test'],
        'handler' => $handler,
        // Matches config/filesystems.php's `s3` disk — see the comment
        // there. Without it, ApplyChecksumMiddleware tries to hash (and
        // therefore seek) the request body before this test's own
        // non-seekable-stream case ever gets there.
        'request_checksum_calculation' => 'when_required',
    ]);
}

/**
 * A tiny, real (GD-encoded) JPEG — G-03's own guidance is to synthesize
 * fixture images in the test rather than check in real photos. Deliberately
 * carries no EXIF: GD cannot write it, so App\Media\Support\ExifFields is
 * unit-tested against exif_read_data()'s own array shape instead of against
 * a file this suite would have to fabricate byte-for-byte.
 */
function syntheticJpeg(int $width = 300, int $height = 200): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            imagesetpixel($image, $x, $y, imagecolorallocate($image, $x % 256, $y % 256, ($x + $y) % 256));
        }
    }

    ob_start();
    imagejpeg($image, quality: 90);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * A MultipartUpload already Completed, with $bytes actually sitting at its
 * quarantine key on the faked `s3` disk — the state G-03's
 * App\Media\Actions\IngestCompletedUpload expects to find one in once
 * MultipartUploadController::complete() has run. Call Storage::fake('s3')
 * before this.
 */
function completedUpload(Gallery $gallery, string $bytes, string $filename = 'IMG_0001.jpg'): MultipartUpload
{
    $upload = MultipartUpload::factory()->for($gallery)->create([
        'filename' => $filename,
        'file_size' => strlen($bytes),
        'status' => MultipartUploadStatus::Completed,
        'completed_at' => now(),
    ]);

    Storage::disk('s3')->put($upload->key, $bytes);

    return $upload;
}

function integerIdPaths(mixed $node, string $path = '$'): array
{
    if (! is_array($node)) {
        return [];
    }

    $found = [];

    foreach ($node as $key => $value) {
        $childPath = $path.'.'.$key;

        if (is_string($key) && (str_ends_with($key, 'id') || str_ends_with($key, 'ids')) && is_int($value)) {
            $found[] = $childPath;
        }

        $found = [...$found, ...integerIdPaths($value, $childPath)];
    }

    return $found;
}

/**
 * E-12's inbound-mail fixtures. Both InboundMailTest and
 * InboundMimeMessageTest need the same tenant secret and the same threaded
 * project, and a global function declared inside one test file collides the
 * moment a second file declares it — the reason contractProject() lives here
 * too.
 */
function inboundDomain(): string
{
    return 'in.nordlicht.test';
}

function inboundThread(string $email = 'lena@example.test'): Thread
{
    $project = contractProject();
    $contact = $project->contacts()->sole();
    $contact->update(['email' => $email]);

    $thread = Thread::factory()->for($project)->create();
    $thread->participants()->create(['contact_id' => $contact->id]);

    return $thread;
}

/** The tenant's inbound secret, set and readable — see InboundAddress. */
function withSecret(string $secret = 'a-tenant-secret'): string
{
    StudioSettings::query()->firstOrFail()->forceFill(['inbound_secret' => $secret])->save();
    app()->forgetInstance(StudioSettingsResolver::class);

    return $secret;
}

/**
 * Ein bestätigter Gast in dieser Galerie und sein signierter Pass.
 *
 * Herz und Kommentar verlangen ihn seit der Gast-Identität: ohne Absender
 * gibt es keine Markierung, die ein zweites Gerät wiederfindet.
 *
 * @return array{0: GalleryGuest, 1: string}
 */
function confirmedGuest(Gallery $gallery, string $email = 'gast@example.com', string $name = 'Gast Gästin'): array
{
    $guest = GalleryGuest::create([
        'gallery_id' => $gallery->id,
        'email' => $email,
        'name' => $name,
        'identifier' => GalleryGuest::identifierFor($gallery, $email),
        'confirmed_at' => now(),
    ]);

    return [$guest, app(GalleryGuestPass::class)->issue($gallery, $guest)];
}
