<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Gallery\Models\Gallery;
use App\Integration\Models\ApiToken;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

function deleteProject(Project $project, Capability $scope = Capability::ManageLeads)
{
    $token = ApiToken::factory()->withScopes($scope)->create();

    return test()
        ->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'delete-'.$project->public_id)
        ->deleteJson("/api/projects/{$project->public_id}");
}

it('deletes a project with everything that only exists as part of it', function () {
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    Lead::factory()->for($project)->create();
    Shoot::factory()->for($project)->create();
    InternalNote::factory()->for($project)->create();

    deleteProject($project)->assertNoContent();

    expect(Project::query()->count())->toBe(0)
        ->and(Lead::query()->count())->toBe(0)
        ->and(Shoot::query()->count())->toBe(0)
        ->and(InternalNote::query()->count())->toBe(0)
        ->and($project->contacts()->count())->toBe(0);

    // The person stays: they are reachable from every other project they are on.
    expect($contact->fresh())->not->toBeNull();
});

it('refuses a project that something more than an import artifact hangs off', function (Closure $attach, string $named) {
    $project = Project::factory()->create();
    $attach($project);

    deleteProject($project)
        ->assertStatus(422)
        ->assertJsonPath('errors.project.0', "The project has a {$named} and cannot be deleted.");

    expect(Project::query()->count())->toBe(1);
})->with([
    'booking' => [fn (Project $project) => markBooked($project), 'booking'],
    'quote' => [fn (Project $project) => Quote::factory()->for($project)->create(), 'quote'],
    'gallery' => [fn (Project $project) => Gallery::factory()->for($project)->create(), 'gallery'],
]);

it('names every blocker, not just the first', function () {
    $project = Project::factory()->create();
    markBooked($project);
    Quote::factory()->for($project)->create();

    deleteProject($project)
        ->assertStatus(422)
        ->assertJsonPath('errors.project.0', 'The project has a booking and quote and cannot be deleted.');
});

it('keeps a project with a payment plan even past the api guard', function () {
    $project = markBooked(Project::factory()->create());
    PaymentPlan::factory()->for($project)->create();

    expect(fn () => $project->delete())->toThrow(QueryException::class);
});

it('refuses a token that does not hold the manage_leads scope', function () {
    $project = Project::factory()->create();

    deleteProject($project, Capability::ManageNotes)->assertForbidden();

    expect(Project::query()->count())->toBe(1);
});

it('refuses a delete without an idempotency key', function () {
    $project = Project::factory()->create();
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->deleteJson("/api/projects/{$project->public_id}")
        ->assertStatus(400);

    expect(Project::query()->count())->toBe(1);
});

it('404s a project that does not exist', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'delete-nothing')
        ->deleteJson('/api/projects/not-a-real-id')
        ->assertNotFound();
});
