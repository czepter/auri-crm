<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractTemplate;
use App\Contracts\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// contractProject() lives in tests/Pest.php — see the note there.

/**
 * Gotenberg is not reachable from this test environment (no Testcontainers
 * or docker-compose service wired into CI/Pest — see docs/contracts.md and
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

    $template = ContractTemplate::factory()->create([
        'body' => '<p>{{ contact.name }} — {{ project.name }} — gebucht am {{ job.bookedAt }}</p>',
    ]);
    $project = contractProject();
    $contract = Contract::factory()->for($project)->for($template, 'template')->create();

    (new RenderContractJob($contract))->handle(app(GotenbergClient::class));

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

it('marks the contract failed and keeps the exception when Gotenberg errors', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('boom', 500)]);

    $contract = Contract::factory()->for(contractProject())->create();

    expect(fn () => (new RenderContractJob($contract))->handle(app(GotenbergClient::class)))
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
    (new RenderContractJob($contract))->handle(app(GotenbergClient::class));

    Http::assertSentCount(1);

    // A second attempt at the same, now-rendered contract — a retried job, or
    // two dispatches racing — must never call Gotenberg again.
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-second', 200)]);
    (new RenderContractJob($contract->fresh()))->handle(app(GotenbergClient::class));

    Http::assertNothingSent();
});
