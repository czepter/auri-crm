<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Shoot;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Integration\Models\ApiToken;
use App\Models\StudioClosure;
use App\Models\StudioSettings;
use App\Models\User;
use App\Numbering\Models\NumberRange;
use App\Portal\Support\LegalTexts;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

function opsToken(Capability ...$scopes): string
{
    return bearerToken(ApiToken::factory()->withScopes(...$scopes)->create());
}

it('assigns crew, lists it and removes an assignment', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManageCrew));
    $shoot = Shoot::factory()->create();
    $user = User::factory()->create(['role' => StaffRole::Contributor]);

    $staffId = $this->getJson('/api/staff')->assertOk()->json('data.0.id');
    expect($staffId)->not->toBeNull();

    $assignmentId = $this->withHeader('Idempotency-Key', 'crew-1')
        ->postJson("/api/shoots/{$shoot->public_id}/crew", ['user' => $user->public_id])
        ->assertCreated()
        ->assertJsonPath('data.userName', $user->name)
        ->json('data.id');

    $this->getJson("/api/shoots/{$shoot->public_id}/crew")
        ->assertOk()
        ->assertJsonPath('data.0.userName', $user->name);

    $this->withHeader('Idempotency-Key', 'crew-2')
        ->deleteJson("/api/crew/{$assignmentId}")
        ->assertNoContent();

    $this->getJson("/api/shoots/{$shoot->public_id}/crew")->assertOk()->assertJsonCount(0, 'data');
});

it('reads studio settings, closures and number ranges', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManageSettings));
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause', 'message' => 'Zurück am 2. Januar.']);

    $this->getJson('/api/studio-settings')->assertOk()->assertJsonStructure(['data' => ['vatMode', 'vatRate', 'quoteValidityDays']]);
    $this->getJson('/api/studio-closures')->assertOk()->assertJsonPath('data.0.label', 'Weihnachtspause')->assertJsonPath('data.0.message', 'Zurück am 2. Januar.');
    $this->getJson('/api/number-ranges')->assertOk()->assertJsonCount(NumberRange::query()->count(), 'data');
});

it('sets legal text links and leaves the others alone', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManageSettings));
    StudioSettings::query()->firstOrFail()->update(['portal_legal_agb_url' => 'https://example.test/agb']);
    StudioSettings::forgetCurrent();

    $this->withHeader('Idempotency-Key', 'legal-1')
        ->patchJson('/api/studio-settings/legal-texts', [
            'widerruf' => 'https://example.test/widerrufsbelehrung',
            'impressum' => 'https://example.test/impressum',
        ])
        ->assertOk()
        ->assertJsonPath('data.widerruf', 'https://example.test/widerrufsbelehrung')
        ->assertJsonPath('data.impressum', 'https://example.test/impressum')
        ->assertJsonPath('data.agb', 'https://example.test/agb');

    expect(LegalTexts::url('widerruf'))->toBe('https://example.test/widerrufsbelehrung');

    $this->withHeader('Idempotency-Key', 'legal-2')
        ->patchJson('/api/studio-settings/legal-texts', ['widerruf' => 'kein-link'])
        ->assertUnprocessable();
});

it('creates a form from a template, updates it, shares it and records an answer', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManageFormTemplates));
    $project = ClientCase::factory()->create();
    $template = FormTemplate::factory()->create();
    $template->fields()->create(['label' => 'Wer kommt?', 'type' => 'text', 'sort_order' => 0]);

    $formId = $this->withHeader('Idempotency-Key', 'form-1')
        ->postJson("/api/cases/{$project->public_id}/forms", ['template' => $template->public_id])
        ->assertCreated()
        ->assertJsonPath('data.fields.0.label', 'Wer kommt?')
        ->json('data.id');

    $this->getJson("/api/cases/{$project->public_id}/forms")->assertOk()->assertJsonCount(1, 'data');

    $fieldId = $this->withHeader('Idempotency-Key', 'form-2')
        ->patchJson("/api/forms/{$formId}", [
            'name' => 'Familie',
            'fields' => [['label' => 'Wer kommt?', 'type' => 'text', 'required' => true]],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Familie')
        ->json('data.fields.0.id');

    $sharedAt = $this->withHeader('Idempotency-Key', 'form-3')
        ->postJson("/api/forms/{$formId}/share", ['shared' => true])
        ->assertOk()
        ->json('data.sharedAt');
    expect($sharedAt)->not->toBeNull();

    $this->withHeader('Idempotency-Key', 'form-3b')
        ->postJson("/api/forms/{$formId}/close", ['closed' => true])
        ->assertOk()
        ->assertJsonPath('data.closedAt', fn ($closedAt) => $closedAt !== null);

    $this->withHeader('Idempotency-Key', 'form-3c')
        ->postJson("/api/forms/{$formId}/close", ['closed' => false])
        ->assertOk()
        ->assertJsonPath('data.closedAt', null);

    $this->withHeader('Idempotency-Key', 'form-4')
        ->postJson("/api/form-fields/{$fieldId}/answer", ['value' => 'Oma und Opa'])
        ->assertOk()
        ->assertJsonPath('data.fields.0.currentAnswer', 'Oma und Opa');

    $this->withHeader('Idempotency-Key', 'form-5')
        ->patchJson("/api/forms/{$formId}", [
            'name' => 'Familie',
            'fields' => [['id' => $fieldId, 'label' => 'Wer kommt alles?', 'type' => 'text', 'required' => true]],
        ])
        ->assertOk()
        ->assertJsonPath('data.fields.0.id', $fieldId)
        ->assertJsonPath('data.fields.0.currentAnswer', 'Oma und Opa');

    $this->withHeader('Idempotency-Key', 'form-6')
        ->patchJson("/api/forms/{$formId}", [
            'name' => 'Familie',
            'fields' => [['id' => $fieldId, 'label' => 'Wer kommt alles?', 'type' => 'text', 'required' => true, 'help' => 'Auch Kinder.']],
        ])
        ->assertOk()
        ->assertJsonPath('data.fields.0.help', 'Auch Kinder.');

    $this->getJson("/api/cases/{$project->public_id}/forms")->assertJsonPath('data.0.fields.0.help', 'Auch Kinder.');

    $this->withHeader('Idempotency-Key', 'form-7')
        ->patchJson("/api/forms/{$formId}", [
            'name' => 'Familie',
            'fields' => [['id' => $fieldId, 'label' => 'Wer kommt alles?', 'type' => 'text', 'required' => true, 'help' => str_repeat('a', 501)]],
        ])
        ->assertUnprocessable();
});

it('adds and removes a shoot timeline entry', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManageShoots));
    $shoot = Shoot::factory()->create();

    $entryId = $this->withHeader('Idempotency-Key', 'timeline-1')
        ->postJson("/api/shoots/{$shoot->public_id}/timeline", ['time' => '14:00', 'title' => 'Ankunft'])
        ->assertCreated()
        ->json('data.id');

    $this->getJson("/api/shoots/{$shoot->public_id}/timeline")->assertOk()->assertJsonCount(1, 'data');

    $this->withHeader('Idempotency-Key', 'timeline-2')
        ->deleteJson("/api/timeline/{$entryId}")
        ->assertNoContent();
});

it('checks a shot list item idempotently rather than toggling it', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManageShoots));
    $shoot = Shoot::factory()->create();

    $itemId = $this->withHeader('Idempotency-Key', 'shot-1')
        ->postJson("/api/shoots/{$shoot->public_id}/shot-list", ['label' => 'Ringe'])
        ->assertCreated()
        ->json('data.id');

    $this->withHeader('Idempotency-Key', 'shot-2')
        ->postJson("/api/shot-list/{$itemId}/check")
        ->assertOk()
        ->assertJsonPath('data.checkedOff', true);

    // A retried check must land on the same state, not flip back off.
    $this->withHeader('Idempotency-Key', 'shot-2')
        ->postJson("/api/shot-list/{$itemId}/check")
        ->assertOk()
        ->assertJsonPath('data.checkedOff', true);
});

it('sets a project explicit portal visibility rather than toggling it', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManagePortalAccess));
    $project = ClientCase::factory()->create(['portal_visible' => false]);

    $this->withHeader('Idempotency-Key', 'visibility-1')
        ->postJson("/api/cases/{$project->public_id}/portal-visibility", ['visible' => true])
        ->assertOk()
        ->assertJsonPath('portalVisible', true);

    // Retrying the same write must not flip it back off.
    $this->withHeader('Idempotency-Key', 'visibility-1')
        ->postJson("/api/cases/{$project->public_id}/portal-visibility", ['visible' => true])
        ->assertOk()
        ->assertJsonPath('portalVisible', true);

    expect($project->fresh()->portal_visible)->toBeTrue();
});

it('refuses studio reads to a token without ManageSettings', function () {
    $this->withHeader('Authorization', 'Bearer '.opsToken(Capability::ManageQuotes));

    $this->getJson('/api/studio-settings')->assertForbidden();
});
