<?php

use App\Documents\Support\InlineHtmlSanitizer;

it('keeps the inline markup a document may carry', function () {
    $html = 'Hallo <b>Lena</b> und <em>Jonas</em><br><a href="https://x.test">Link</a>';

    expect(InlineHtmlSanitizer::inline($html))->toBe($html);
});

it('keeps a merge-field chip intact, since the serializer reads it back', function () {
    $chip = '<span class="doc-mf" data-mf="contact.name">Lena Meier</span>';

    expect(InlineHtmlSanitizer::inline($chip))->toBe($chip);
});

it('unwraps a disallowed tag but keeps the text inside it', function () {
    expect(InlineHtmlSanitizer::inline('<div>Absatz</div>'))->toBe('Absatz')
        ->and(InlineHtmlSanitizer::inline('<b onclick="x()">fett</b>'))->toBe('<b>fett</b>');
});

it('drops script and style whole, source text included, since neither is document text', function () {
    expect(InlineHtmlSanitizer::inline('<script>alert(1)</script>'))->toBe('')
        ->and(InlineHtmlSanitizer::inline('<style>.x{color:red}</style>'))->toBe('')
        ->and(InlineHtmlSanitizer::inline('Vertrag <script>alert(1)</script>gültig'))->toBe('Vertrag gültig');
});

it('drops an img with an onerror payload entirely, tag and attributes', function () {
    expect(InlineHtmlSanitizer::inline('<img src=x onerror=alert(1)>'))->toBe('');
});

it('strips an href that is not http, https or mailto', function () {
    expect(InlineHtmlSanitizer::inline('<a href="javascript:alert(1)">klick</a>'))->toBe('<a>klick</a>')
        ->and(InlineHtmlSanitizer::inline("<a href='mailto:a@b.test'>mail</a>"))->toBe('<a href="mailto:a@b.test">mail</a>');
});

it('leaves German text and its umlauts exactly as written', function () {
    expect(InlineHtmlSanitizer::inline('Vertragsgemäß, für Grüße'))->toBe('Vertragsgemäß, für Grüße');
});

it('sanitises every string leaf in a blocks payload', function () {
    $blocks = ['blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'Hallo <img src=x onerror=alert(1)>']],
        ['type' => 'columns', 'data' => ['left' => '<b>links</b>', 'right' => '<script>x()</script>rechts']],
    ]];

    $clean = InlineHtmlSanitizer::blocks($blocks);

    expect($clean['blocks'][0]['data']['text'])->toBe('Hallo ')
        ->and($clean['blocks'][1]['data']['left'])->toBe('<b>links</b>')
        ->and($clean['blocks'][1]['data']['right'])->toBe('rechts');
});

it('leaves a legacy rawHtml block alone, since it is a pre-editor body by definition', function () {
    $blocks = ['blocks' => [['type' => 'rawHtml', 'data' => ['html' => '<div class="alt"><p>Alt</p></div>']]]];

    expect(InlineHtmlSanitizer::blocks($blocks)['blocks'][0]['data']['html'])
        ->toBe('<div class="alt"><p>Alt</p></div>');
});
