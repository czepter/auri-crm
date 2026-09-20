<?php

use App\Automation\Models\AutomationRule;
use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\StarterTemplateCatalog;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    StudioSettings::query()->update(['client_register' => 'formal']);
    Cache::store('redis')->flush();

    $this->artisan('automation:seed-default-rules');
    (new SeedStarterTemplates)(['mail-quote-sent', 'mail-invoice-sent'], null, 'formal');

    $this->owner = staffMember(StaffRole::Owner);
});

function currentMail(string $name): DocumentTemplate
{
    return DocumentTemplate::query()->current()->where('name', $name)->sole();
}

function informalBlocks(string $key): array
{
    return StarterTemplateCatalog::find($key, 'informal')['blocks'];
}

it('switches untouched automations and starter templates and counts what the studio changed', function () {
    AutomationRule::query()->where('key', 'quote-follow-up')->update(['notification_body' => 'Eigener Text.']);
    currentMail('Rechnung verschickt')->newVersion([
        'name' => 'Rechnung verschickt',
        'body' => '<p>Eigener Text.</p>',
        'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Eigener Text.']]]],
    ]);

    $preview = $this->actingAs($this->owner, 'staff')
        ->getJson('/einstellungen/anrede-vorschau?ziel=informal')
        ->assertOk()
        ->json();

    $this->actingAs($this->owner, 'staff')
        ->patch('/einstellungen', ['client_register' => 'informal', 'adapt_existing' => true])
        ->assertRedirect();

    $audit = AuditLog::where('event', 'studio.register_adapted')->sole();

    expect($preview['skipped'])->toBe(2)
        ->and($preview['adapted'])->toBeGreaterThan(0)
        ->and($audit->context)->toMatchArray(['adapted' => $preview['adapted'], 'skipped' => $preview['skipped']])
        ->and(AutomationRule::query()->where('key', 'quote-no-reply')->value('notification_subject'))->toBe('Dein Angebot für {{ project.name }}')
        ->and(AutomationRule::query()->where('key', 'quote-follow-up')->value('notification_body'))->toBe('Eigener Text.')
        ->and(currentMail('Angebot verschickt')->blocks)->toEqual(informalBlocks('mail-quote-sent'))
        ->and(currentMail('Rechnung verschickt')->blocks['blocks'][0]['data']['text'])->toBe('Eigener Text.');
});

it('leaves everything as it is without adapt_existing', function () {
    $this->actingAs($this->owner, 'staff')
        ->patch('/einstellungen', ['client_register' => 'informal'])
        ->assertRedirect();

    expect(StudioSettings::query()->sole()->client_register)->toBe('informal')
        ->and(AutomationRule::query()->where('key', 'quote-no-reply')->value('notification_subject'))->toBe('Ihr Angebot für {{ project.name }}')
        ->and(AuditLog::where('event', 'studio.register_adapted')->exists())->toBeFalse();
});

it('rejects adapt_existing without client_register', function () {
    $this->actingAs($this->owner, 'staff')
        ->patch('/einstellungen', ['adapt_existing' => true])
        ->assertInvalid(['adapt_existing']);
});
