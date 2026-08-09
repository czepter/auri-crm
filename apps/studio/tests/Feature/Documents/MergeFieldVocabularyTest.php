<?php

use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;

it('has a sample value for every documented path', function () {
    $sample = MergeFieldVocabulary::sample();

    foreach (MergeFieldVocabulary::paths() as $path) {
        expect(data_get($sample, $path))->not->toBeNull()->and(data_get($sample, $path))->not->toBe('');
    }
});

/**
 * T-12's own acceptance criterion: "a preview renders every field in the
 * vocabulary against sample data with no unresolved placeholder." Builds a
 * body carrying every documented path and proves the resolved output has
 * nothing left over.
 */
it('resolves a body carrying every vocabulary path with no unresolved placeholder', function () {
    $body = collect(MergeFieldVocabulary::paths())
        ->map(fn (string $path) => "{{ {$path} }}")
        ->implode(' ');

    $resolved = MergeFieldResolver::resolve($body, MergeFieldVocabulary::sample());

    expect($resolved)->not->toContain('{{');
});
