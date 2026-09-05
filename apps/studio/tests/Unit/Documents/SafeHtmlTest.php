<?php

use App\Documents\Support\SafeHtml;

it('spots an unsafe href in every quoting style', function () {
    expect(SafeHtml::hasUnsafeHref('<a href="javascript:alert(1)">x</a>'))->toBeTrue()
        ->and(SafeHtml::hasUnsafeHref("<a href='javascript:alert(1)'>x</a>"))->toBeTrue()
        ->and(SafeHtml::hasUnsafeHref('<a href=javascript:alert(1)>x</a>'))->toBeTrue()
        ->and(SafeHtml::hasUnsafeHref('<a href = "data:text/html;base64,PHNjcmlwdD4=">x</a>'))->toBeTrue();
});

it('passes the three schemes a document may link to', function () {
    expect(SafeHtml::hasUnsafeHref('<a href="https://x.test">x</a>'))->toBeFalse()
        ->and(SafeHtml::hasUnsafeHref('<a href="http://x.test">x</a>'))->toBeFalse()
        ->and(SafeHtml::hasUnsafeHref("<a href='mailto:a@b.test'>x</a>"))->toBeFalse();
});

it('passes html with no links at all', function () {
    expect(SafeHtml::hasUnsafeHref('<p>Vertrag mit {{ contact.name }}</p>'))->toBeFalse();
});

it('treats a relative href as unsafe, since a document has no site to be relative to', function () {
    expect(SafeHtml::hasUnsafeHref('<a href="/intern/kunden">x</a>'))->toBeTrue();
});
