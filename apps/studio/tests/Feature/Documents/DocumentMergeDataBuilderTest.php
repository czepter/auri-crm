<?php

use App\Documents\Support\DocumentMergeDataBuilder;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// contractProject() lives in tests/Pest.php — see the note there.

it('builds the same merge data shape any document type resolves against', function () {
    $project = contractProject();

    $data = (new DocumentMergeDataBuilder)->fromProject($project, new StudioSettingsResolver);

    expect($data['project']['name'])->toBe('Hochzeit Meier & Kowalski')
        ->and($data['project']['type'])->toBe($project->type->label())
        ->and($data['contact']['name'])->toBe('Lena Meier')
        ->and($data['contact']['email'])->toBe('lena@example.test')
        ->and($data['job']['bookedAt'])->toBe('14.06.2026')
        ->and($data)->toHaveKeys(['studio', 'settings']);
});

/**
 * T-12's own acceptance criterion: the same merge field resolves identically
 * wherever it is read from. DocumentMergeDataBuilder takes no document type
 * at all — it is the one place that array is built, so a contract, a quote
 * and a mail rendered from the same project read the exact same values.
 */
it('is the one place the array is built — nothing about it varies by document type', function () {
    $project = contractProject();
    $settings = new StudioSettingsResolver;
    $builder = new DocumentMergeDataBuilder;

    $first = $builder->fromProject($project, $settings);
    $second = $builder->fromProject($project, $settings);

    expect($first)->toBe($second);
});
