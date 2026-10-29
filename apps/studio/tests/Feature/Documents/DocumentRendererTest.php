<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ships css for all five quote layout wrappers', function () {
    [$user, $project] = studioWithProject();

    $html = app(DocumentRenderer::class)->wrap(DocumentTemplateType::Quote, '<p>x</p>');

    foreach (['classic', 'minimal', 'letter', 'header_band', 'structured'] as $layout) {
        expect($html)->toContain('.doc-layout-'.$layout);
    }
});
