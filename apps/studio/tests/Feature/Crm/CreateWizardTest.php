<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('opens each wizard without a project', function (string $path) {
    expect(props($path)['project'])->toBeNull();
})->with(['/shootings/neu', '/angebote/neu', '/frageboegen/neu']);

it('carries the chosen project, never an integer identifier', function (string $path) {
    $project = ClientCase::factory()->create(['name' => 'Rahn, Marie & Jonas']);

    $chosen = props($path.'?projekt='.$project->public_id)['project'];

    expect($chosen['id'])->toBe($project->public_id)
        ->and($chosen['name'])->toBe('Rahn, Marie & Jonas')
        ->and($chosen['isBooked'])->toBeFalse();
})->with(['/shootings/neu', '/angebote/neu', '/frageboegen/neu']);

it('seeds the shoot date from the dashboard week', function () {
    expect(props('/shootings/neu?datum=2026-10-21')['date'])->toBe('2026-10-21');
});

it('leads a shoot created from the wizard to its project', function () {
    $project = ClientCase::factory()->create();

    $this->post('/auftraege/'.$project->public_id.'/shootings', ['wizard' => true, 'kind' => 'wedding'])
        ->assertRedirect('/auftraege/'.$project->public_id.'/shootings');

    expect($project->shoots()->count())->toBe(1);
});

it('returns a questionnaire seeded in the wizard to the wizard, ready to personalise', function () {
    $project = ClientCase::factory()->create();
    $template = FormTemplate::factory()->create(['is_active' => true, 'name' => 'Ablaufplan']);

    $response = $this->post('/auftraege/'.$project->public_id.'/frageboegen', ['template' => $template->public_id, 'wizard' => true]);

    $instance = $project->formInstances()->sole();
    $response->assertRedirect('/frageboegen/neu?'.http_build_query(['projekt' => $project->public_id, 'fragebogen' => $instance->public_id]));

    expect(props('/frageboegen/neu?projekt='.$project->public_id.'&fragebogen='.$instance->public_id)['instance']['name'])->toBe('Ablaufplan');
});

it('shows only the project\'s drafts in the quote wizard workspace props', function () {
    $project = ClientCase::factory()->create();

    $this->post('/auftraege/'.$project->public_id.'/angebote');

    expect(props('/angebote/neu?projekt='.$project->public_id)['quotes'])->toHaveCount(1)
        ->and(props('/angebote/neu?projekt='.$project->public_id))->not->toHaveKey('paymentPlan');
});
