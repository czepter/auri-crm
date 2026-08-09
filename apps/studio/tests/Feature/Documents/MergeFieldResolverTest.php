<?php

use App\Documents\Support\MergeFieldResolver;

it('resolves a dot-path merge field against the given data', function () {
    $resolved = MergeFieldResolver::resolve(
        '<p>Vertrag mit {{ contact.name }} über {{ project.name }}.</p>',
        ['contact' => ['name' => 'Lena Meier'], 'project' => ['name' => 'Hochzeit Meier']],
    );

    expect($resolved)->toBe('<p>Vertrag mit Lena Meier über Hochzeit Meier.</p>');
});

it('tolerates whitespace inside the braces', function () {
    expect(MergeFieldResolver::resolve('{{project.name}} / {{  project.name  }}', ['project' => ['name' => 'X']]))
        ->toBe('X / X');
});

it('resolves an unknown path to an empty string rather than failing', function () {
    expect(MergeFieldResolver::resolve('Hallo {{ project.nope }}!', ['project' => ['name' => 'X']]))
        ->toBe('Hallo !');
});

it('leaves text with no merge fields untouched', function () {
    expect(MergeFieldResolver::resolve('<p>Kein Platzhalter hier.</p>', []))
        ->toBe('<p>Kein Platzhalter hier.</p>');
});

it('resolves the same field wherever it repeats', function () {
    expect(MergeFieldResolver::resolve('{{ project.name }} … {{ project.name }}', ['project' => ['name' => 'X']]))
        ->toBe('X … X');
});

/**
 * T-12's own acceptance criterion: "the same merge field resolves
 * identically in a contract, a quote and a mail." MergeFieldResolver never
 * takes a document type at all — it resolves the same body against the same
 * data no matter which DocumentTemplateType eventually wraps the result —
 * which is what makes that criterion true by construction rather than by
 * convention three call sites have to keep agreeing on.
 */
it('resolves the same body identically no matter which document type calls it', function () {
    $body = '<p>{{ contact.name }} — {{ project.name }} — {{ studio.name }}</p>';
    $data = ['contact' => ['name' => 'Lena Meier'], 'project' => ['name' => 'Hochzeit'], 'studio' => ['name' => 'Atelier Nordlicht']];

    $contract = MergeFieldResolver::resolve($body, $data);
    $quote = MergeFieldResolver::resolve($body, $data);
    $mail = MergeFieldResolver::resolve($body, $data);

    expect($contract)->toBe($quote)->and($quote)->toBe($mail)
        ->and($contract)->toBe('<p>Lena Meier — Hochzeit — Atelier Nordlicht</p>');
});
