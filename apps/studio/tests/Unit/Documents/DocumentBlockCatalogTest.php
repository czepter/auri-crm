<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentBlockCatalog;

it('offers layout blocks to a contract but never a page-breaking column layout to a mail', function () {
    $contract = collect(DocumentBlockCatalog::for(DocumentTemplateType::Contract))->pluck('type');
    $mail = collect(DocumentBlockCatalog::for(DocumentTemplateType::Mail))->pluck('type');

    expect($contract)->toContain('columns', 'spacer', 'table')
        ->and($mail)->toContain('paragraph', 'table')
        ->and($mail)->not->toContain('columns')
        ->and($mail)->not->toContain('spacer');
});

it('offers the merge-field tool to every type, flagged inline', function () {
    foreach (DocumentTemplateType::cases() as $type) {
        $field = collect(DocumentBlockCatalog::for($type))->firstWhere('type', 'mergeField');

        expect($field)->not->toBeNull()
            ->and($field['inline'])->toBeTrue()
            ->and($field['group'])->toBe('data');
    }
});

it('never lets an inline tool through as a block', function () {
    expect(DocumentBlockCatalog::allows(DocumentTemplateType::Contract, 'mergeField'))->toBeFalse();
});

it('allows a legacy raw-html block for every type so imported bodies stay saveable', function () {
    foreach (DocumentTemplateType::cases() as $type) {
        expect(DocumentBlockCatalog::allows($type, 'rawHtml'))->toBeTrue();
    }
});

it('rejects a block type nobody implements', function () {
    expect(DocumentBlockCatalog::allows(DocumentTemplateType::Contract, 'attachment'))->toBeFalse()
        ->and(DocumentBlockCatalog::allows(DocumentTemplateType::Quote, 'nonsense'))->toBeFalse();
});

it('keeps the contract blocks off an email, where a signature line and a page break mean nothing', function () {
    foreach (['signature', 'clause', 'pagebreak', 'lineItems'] as $blockType) {
        expect(DocumentBlockCatalog::allows(DocumentTemplateType::Mail, $blockType))->toBeFalse();
    }

    expect(DocumentBlockCatalog::allows(DocumentTemplateType::Contract, 'signature'))->toBeTrue()
        ->and(DocumentBlockCatalog::allows(DocumentTemplateType::Quote, 'lineItems'))->toBeTrue()
        ->and(DocumentBlockCatalog::allows(DocumentTemplateType::Contract, 'lineItems'))->toBeFalse();
});

it('labels every entry, so the palette never renders a bare key', function () {
    foreach (DocumentTemplateType::cases() as $type) {
        foreach (DocumentBlockCatalog::for($type) as $entry) {
            expect($entry['label'])->not->toBeEmpty();
        }
    }
});
