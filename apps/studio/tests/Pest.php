<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractTemplate;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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
    ->beforeEach(fn () => $this->withoutVite())
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

    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();

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
        ->for(ContractTemplate::factory(), 'template')
        ->create(['status' => ContractStatus::Pending]);

    $path = "contracts/test/{$contract->public_id}.pdf";
    Storage::disk('local')->put($path, $bytes);
    $contract->update(['status' => ContractStatus::Rendered, 'artifact_path' => $path, 'rendered_at' => now()]);

    return $contract->fresh();
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
