<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentBlockCatalog;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Documents\Support\StarterBlockSerializer;
use App\Documents\Support\StarterTemplateCatalog;

// No RefreshDatabase: the catalog is a constant. If any of this ever needs a
// database it has stopped being one.

/** Every `{{ … }}` path an entry references, found in every block field that can carry text. */
function pathsIn(array $entry): array
{
    preg_match_all(
        '/\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/',
        json_encode($entry['blocks'], JSON_UNESCAPED_UNICODE),
        $matches,
    );

    return array_values(array_unique($matches[1] ?? []));
}

it('offers at least one starter for every document type', function () {
    foreach (DocumentTemplateType::cases() as $type) {
        expect(StarterTemplateCatalog::for($type))->not->toBeEmpty("no starter for {$type->value}");
    }
});

it('never offers a block the server would refuse on save', function () {
    // The invariant that makes the catalog safe to extend: a contributor
    // adding a two-column block to a mail starter finds out here rather than
    // from a studio whose save failed.
    foreach (StarterTemplateCatalog::all() as $entry) {
        $type = DocumentTemplateType::from($entry['type']);

        foreach ($entry['blocks']['blocks'] as $block) {
            expect(DocumentBlockCatalog::allows($type, $block['type']))
                ->toBeTrue("{$entry['key']} uses a {$block['type']} block, which {$type->value} does not allow");
        }
    }
});

it('never ships a merge path the vocabulary does not know', function () {
    // A path the vocabulary lacks resolves to an empty string, so this would
    // otherwise surface as a sentence quietly missing its subject in front of
    // a client.
    $known = MergeFieldVocabulary::paths();

    foreach (StarterTemplateCatalog::all() as $entry) {
        foreach (pathsIn($entry) as $path) {
            expect([$entry['key'], ...$known])->toContain($path);
        }
    }
});

it('renders every starter against sample data with nothing left unresolved', function () {
    foreach (StarterTemplateCatalog::all() as $entry) {
        $html = StarterBlockSerializer::toHtml($entry['blocks']);
        $resolved = MergeFieldResolver::resolve($html, MergeFieldVocabulary::sample());

        expect($resolved)->not->toContain('{{', "{$entry['key']} still holds a placeholder after resolution");
    }
});

it('only uses blocks the server-side serializer can render', function () {
    // T-18 seeds without a browser, so a block the PHP serializer cannot
    // render would seed a template with a hole in it.
    foreach (StarterTemplateCatalog::all() as $entry) {
        foreach ($entry['blocks']['blocks'] as $block) {
            expect(StarterBlockSerializer::SUPPORTED)->toContain($block['type']);
        }
    }
});

it('gives every entry a key that is unique, and a description that says what it is', function () {
    $keys = collect(StarterTemplateCatalog::all())->pluck('key');

    expect($keys->duplicates())->toBeEmpty()
        ->and($keys->count())->toBeGreaterThan(5);

    foreach (StarterTemplateCatalog::all() as $entry) {
        expect($entry['name'])->not->toBe('')
            ->and(mb_strlen($entry['description']))->toBeGreaterThan(10, "{$entry['key']} has no usable description");
    }
});

it('marks the legal skeletons as a starting point, and only where the studio reads it', function () {
    // The disclaimer belongs in the description, where it is read while
    // choosing — never in a body a client receives.
    foreach (['contract-werkvertrag', 'invoice-standard', 'invoice-small-business'] as $key) {
        $entry = StarterTemplateCatalog::find($key);

        expect(mb_strtolower($entry['description']))->toContain('ausgangspunkt');

        $body = StarterBlockSerializer::toHtml($entry['blocks']);
        expect(mb_strtolower($body))
            ->not->toContain('keine rechtsberatung')
            ->not->toContain('keine steuerberatung');
    }
});

it('contains no placeholder prose, in any entry', function () {
    // T-17's own acceptance line: a catalog of lorem ipsum is worse than an
    // empty canvas, because it has to be deleted first.
    $forbidden = ['lorem', 'ipsum', 'hier text', 'text einfügen', 'tbd', 'todo', 'xxx', '[…]', 'platzhalter'];

    foreach (StarterTemplateCatalog::all() as $entry) {
        $body = mb_strtolower(StarterBlockSerializer::toHtml($entry['blocks']));

        foreach ($forbidden as $needle) {
            expect($body)->not->toContain($needle, "{$entry['key']} contains placeholder prose: {$needle}");
        }
    }
});

it('keeps the Widerruf clause as a merge field rather than typed text', function () {
    // So it disappears from a rendered contract when the studio switches it
    // off — T-05 owns that decision, not the wording.
    $contract = StarterTemplateCatalog::find('contract-werkvertrag');
    $body = StarterBlockSerializer::toHtml($contract['blocks']);

    expect($body)->toContain('{{ settings.widerrufText }}')
        ->and(mb_strtolower($body))->not->toContain('vierzehn tagen ohne angabe von gründen');
});

it('offers the wedding quote for weddings and the generic one always', function () {
    $quotes = collect(StarterTemplateCatalog::for(DocumentTemplateType::Quote));

    expect($quotes->firstWhere('key', 'quote-generic')['shootKind'])->toBeNull()
        ->and($quotes->firstWhere('key', 'quote-wedding')['shootKind'])->toBe('Hochzeitstag');
});

it('repeats neither the signature nor the legal footer in a mail starter', function () {
    // T-06 appends both to every outgoing mail; a starter carrying them would
    // send them twice.
    foreach (StarterTemplateCatalog::for(DocumentTemplateType::Mail) as $entry) {
        $body = mb_strtolower(StarterBlockSerializer::toHtml($entry['blocks']));

        expect($body)
            ->not->toContain('mit freundlichen grüßen')
            ->not->toContain('{{ studio.taxnumber }}')
            ->not->toContain('ust-idnr');
    }
});

it('pre-ticks a default set that exists and covers the trading documents', function () {
    $defaults = StarterTemplateCatalog::onboardingDefaults('standard');

    foreach ($defaults as $key) {
        expect(StarterTemplateCatalog::find($key))->not->toBeNull("unknown default: {$key}");
    }

    $types = collect($defaults)->map(fn (string $key) => StarterTemplateCatalog::find($key)['type'])->unique();

    expect($types)->toContain('quote', 'contract', 'invoice', 'mail');

    // A studio that shoots no weddings should not finish onboarding with a
    // wedding quote.
    expect($defaults)->not->toContain('quote-wedding');
});

it('swaps the invoice for the Kleinunternehmer variant', function () {
    expect(StarterTemplateCatalog::onboardingDefaults('small_business'))
        ->toContain('invoice-small-business')
        ->not->toContain('invoice-standard');
});

// --- the serializer the seeding path depends on -------------------------

it('renders each supported block the way serialize.ts does', function () {
    expect(StarterBlockSerializer::toHtml(['blocks' => [
        ['type' => 'header', 'data' => ['text' => 'Titel', 'level' => 2]],
        ['type' => 'paragraph', 'data' => ['text' => 'Ein Satz.']],
        ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Eins', 'Zwei']]],
        ['type' => 'delimiter', 'data' => []],
    ]]))->toBe('<h2>Titel</h2><p>Ein Satz.</p><ul><li>Eins</li><li>Zwei</li></ul><hr class="doc-rule">');
});

it('refuses a block it cannot render rather than dropping it silently', function () {
    StarterBlockSerializer::toHtml(['blocks' => [['type' => 'columns', 'data' => []]]]);
})->throws(InvalidArgumentException::class);
