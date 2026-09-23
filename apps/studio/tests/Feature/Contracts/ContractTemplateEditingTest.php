<?php

use App\Contracts\Actions\EnsureContractArtifact;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Crm\Models\ClientCase;
use App\Documents\Models\DocumentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('leaves an already-rendered contract byte-for-byte untouched after its template is edited', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-original-bytes', 200)]);

    $template = DocumentTemplate::factory()->create(['body' => '<p>{{ project.name }}</p>']);
    $contract = Contract::factory()
        ->for(ClientCase::factory(), 'case')
        ->for($template, 'template')
        ->create();

    (new RenderContractJob($contract))->handle(app(EnsureContractArtifact::class));
    $contract->refresh();

    expect($contract->status)->toBe(ContractStatus::Rendered);
    $bytesBefore = Storage::disk('local')->get($contract->artifact_path);
    $pathBefore = $contract->artifact_path;

    // Editing the template creates a new version — never mutates $template.
    $newVersion = $template->newVersion(['name' => $template->name, 'body' => '<p>Ganz anderer Text</p>']);

    $contract->refresh();
    expect($contract->contract_template_id)->toBe($template->id)
        ->and($contract->artifact_path)->toBe($pathBefore)
        ->and(Storage::disk('local')->get($contract->artifact_path))->toBe($bytesBefore);

    // The template row the contract still points at is exactly what it was.
    $template->refresh();
    expect($template->body)->toBe('<p>{{ project.name }}</p>')
        ->and($template->is_current)->toBeFalse()
        ->and($newVersion->is_current)->toBeTrue();
});

it('does not call Gotenberg or the queue when editing a template', function () {
    Http::fake();

    $template = DocumentTemplate::factory()->create();

    $template->newVersion(['name' => 'Neu', 'body' => '<p>Neu</p>']);

    Http::assertNothingSent();
});
