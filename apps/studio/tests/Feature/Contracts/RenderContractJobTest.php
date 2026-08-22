<?php

use App\Contracts\Actions\EnsureContractArtifact;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Documents\Models\DocumentTemplate;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// contractProject() lives in tests/Pest.php — see the note there.

/**
 * Gotenberg is not reachable from this test environment (no Testcontainers
 * or docker-compose service wired into CI/Pest — see docs/documents.md and
 * the pull request description). Every test here that would call it mocks
 * the HTTP boundary with Http::fake(); MergeFieldResolverTest proves the
 * resolution logic itself with no HTTP involved at all.
 */
it('dispatches onto the render queue and nothing runs inline', function () {
    Queue::fake();

    $contract = Contract::factory()->for(contractProject())->create();

    RenderContractJob::dispatch($contract)->onQueue('render');

    Queue::assertPushedOn('render', RenderContractJob::class);
});

it('resolves merge fields from the project at render time and stores the artifact Gotenberg returned', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-fake-bytes', 200)]);

    $template = DocumentTemplate::factory()->create([
        'body' => '<p>{{ contact.name }} — {{ project.name }} — gebucht am {{ job.bookedAt }}</p>',
    ]);
    $project = contractProject();
    $contract = Contract::factory()->for($project)->for($template, 'template')->create();

    (new RenderContractJob($contract))->handle(app(EnsureContractArtifact::class));

    Http::assertSent(function (HttpRequest $request) {
        $body = (string) $request->body();

        return str_contains($body, 'Lena Meier')
            && str_contains($body, 'Hochzeit Meier & Kowalski')
            && str_contains($body, '14.06.2026')
            && ! str_contains($body, '{{');
    });

    $contract->refresh();
    expect($contract->status)->toBe(ContractStatus::Rendered)
        ->and($contract->artifact_path)->not->toBeNull()
        ->and($contract->rendered_at)->not->toBeNull();

    Storage::disk('local')->assertExists($contract->artifact_path);
    expect(Storage::disk('local')->get($contract->artifact_path))->toBe('%PDF-fake-bytes');
});

it('resolves studio.* merge fields from T-02\'s settings row, including the one stored logo path', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-fake-bytes', 200)]);

    $owner = staffMember();
    $settings = new StudioSettingsResolver;
    $settings->set('legal_name', 'Atelier Nordlicht Fotografie GmbH', $owner);
    $settings->set('city', 'Hamburg', $owner);
    $settings->setLogo(UploadedFile::fake()->image('logo.png'), $owner);

    $template = DocumentTemplate::factory()->create([
        'body' => '<p>{{ studio.name }}, {{ studio.city }} — Logo: {{ studio.logoPath }}</p>',
    ]);
    $contract = Contract::factory()->for(contractProject())->for($template, 'template')->create();

    (new RenderContractJob($contract))->handle(app(EnsureContractArtifact::class));

    $expectedLogoPath = (new StudioSettingsResolver)->logoPath();
    expect($expectedLogoPath)->not->toBeNull();

    Http::assertSent(function (HttpRequest $request) use ($expectedLogoPath) {
        $body = (string) $request->body();

        return str_contains($body, 'Atelier Nordlicht Fotografie GmbH')
            && str_contains($body, 'Hamburg')
            && str_contains($body, $expectedLogoPath)
            && ! str_contains($body, '{{');
    });
});

it('renders the configured Widerruf text when T-05\'s setting is switched on', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-fake-bytes', 200)]);

    $settings = new StudioSettingsResolver;
    $settings->set('widerruf_included', true);
    $settings->set('widerruf_text', 'Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen zu widerrufen.');

    $template = DocumentTemplate::factory()->create(['body' => '<p>{{ settings.widerrufText }}</p>']);
    $contract = Contract::factory()->for(contractProject())->for($template, 'template')->create();

    (new RenderContractJob($contract))->handle(app(EnsureContractArtifact::class));

    Http::assertSent(fn (HttpRequest $request) => str_contains(
        (string) $request->body(),
        'Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen zu widerrufen.'
    ));
});

it('renders nothing for the Widerruf merge field when the setting is switched off', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-fake-bytes', 200)]);

    $settings = new StudioSettingsResolver;
    $settings->set('widerruf_included', false);
    $settings->set('widerruf_text', 'Dieser Text darf niemals gerendert werden.');

    $template = DocumentTemplate::factory()->create(['body' => '<p>Vor[{{ settings.widerrufText }}]Nach</p>']);
    $contract = Contract::factory()->for(contractProject())->for($template, 'template')->create();

    (new RenderContractJob($contract))->handle(app(EnsureContractArtifact::class));

    Http::assertSent(function (HttpRequest $request) {
        $body = (string) $request->body();

        return str_contains($body, 'Vor[]Nach')
            && ! str_contains($body, 'Dieser Text darf niemals gerendert werden.');
    });
});

it('marks the contract failed and keeps the exception when Gotenberg errors', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('boom', 500)]);

    $contract = Contract::factory()->for(contractProject())->create();

    expect(fn () => (new RenderContractJob($contract))->handle(app(EnsureContractArtifact::class)))
        ->toThrow(RequestException::class);

    $contract->refresh();
    expect($contract->status)->toBe(ContractStatus::Failed)
        ->and($contract->error)->not->toBeNull()
        ->and($contract->artifact_path)->toBeNull();
});

it('never re-renders an already-rendered contract', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-first', 200)]);

    $contract = Contract::factory()->for(contractProject())->create();
    (new RenderContractJob($contract))->handle(app(EnsureContractArtifact::class));

    Http::assertSentCount(1);

    // A second attempt at the same, now-rendered contract — a retried job, or
    // two dispatches racing — must never call Gotenberg again.
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-second', 200)]);
    (new RenderContractJob($contract->fresh()))->handle(app(EnsureContractArtifact::class));

    Http::assertNothingSent();
});
