<?php

// Order and rules: milestone architecture-rework, #555.
// A module may use the kernel and the modules to its left. Http, Console, Mcp
// and Screens are consumer code: they may use anything, nothing may use them.
// Models may use models of any module, for Eloquent relations.

$kernel = ['App\Concerns', 'App\Support', 'App\Enums', 'App\Models', 'App\Data', 'App\Jobs', 'App\Notifications'];

$modules = [
    'App\Numbering', 'App\Documents', 'App\Media', 'App\Crm',
    'App\Catalog', 'App\Engagement', 'App\Portal', 'App\Quotes', 'App\Contracts', 'App\Payments',
    'App\Gallery', 'App\Automation', 'App\Settings', 'App\Reporting', 'App\Privacy',
    'App\Integration', 'App\Help', 'App\Releases', 'App\Staff',
];

$wiring = ['App\Http', 'App\Policies', 'App\Providers', 'App\Templates', 'App\Migration'];

// Today's violations by the issue that removes them. Only ever delete lines.
$baseline = [
    558 => [
        'App\Automation\Actions\FireAutomationRule',
        'App\Automation\Actions\SeedDefaultAutomationRules',
        'App\Automation\Actions\SweepDateOffsetAutomationRules',
        'App\Contracts\Actions\EnsureContractArtifact',
        'App\Crm\Actions\ApplyTaskTemplates',
        'App\Crm\Models\Contact',
        'App\Crm\Models\TaskTemplate',
        'App\Crm\Support\TaskAnchors',
        'App\Documents\Support\DocumentRenderer',
        'App\Engagement\Actions\SendStaffReply',
        'App\Gallery\Actions\PublishGallery',
        'App\Gallery\Actions\RequestGalleryEmailVerification',
        'App\Gallery\Actions\RequestGalleryGuestCode',
        'App\Gallery\Models\Gallery',
        'App\Gallery\Notifications\GalleryEmailVerificationLink',
        'App\Gallery\Notifications\GalleryGuestCode',
        'App\Notifications\Support\StudioMailFooter',
        'App\Numbering\Actions\AllocateDocumentNumber',
        'App\Payments\Actions\ConfirmInstallmentCheckoutSession',
        'App\Payments\Actions\CreateInstallmentCheckoutSession',
        'App\Payments\Actions\DetectOverdueInstallments',
        'App\Payments\Actions\IssueCreditNote',
        'App\Payments\Actions\IssueInvoice',
        'App\Payments\Actions\RenderInvoicePdf',
        'App\Payments\Support\InvoiceDocument',
        'App\Payments\Support\InvoiceFigures',
        'App\Portal\Actions\RemindClientTasks',
        'App\Portal\Actions\RequestClientAccess',
        'App\Portal\Notifications\ClientAccessLink',
        'App\Portal\Notifications\EmailChangeConfirmation',
        'App\Portal\Support\PortalSettings',
        'App\Quotes\Actions\CreateQuoteAddendum',
        'App\Quotes\Actions\DuplicateQuote',
        'App\Quotes\Actions\MarkQuoteSent',
        'App\Quotes\Actions\RenderQuotePdf',
        'App\Quotes\Actions\ReviseQuote',
        'App\Quotes\Data\QuoteData',
        'App\Quotes\Models\Quote',
        'App\Quotes\Notifications\QuoteSent',
        'App\Quotes\Support\SampleQuoteData',
        'App\Settings\Support\StudioSettingsResolver',
    ],
    565 => [
        'App\Documents\Data\MailTemplateData',
        'App\Documents\Support\DocumentMergeDataBuilder',
    ],
];
$baseline = array_merge(...array_values($baseline));

// Reads names from tokens rather than through arch(): Pest's parser keeps an
// AST per file per namespace queried and ran out of 512 MB after the suite.
$violations = function () use ($kernel, $modules, $wiring): array {
    $consumer = fn (string $namespace): array => [
        "$namespace\\Http", "$namespace\\Console", "$namespace\\Mcp", "$namespace\\Screens",
    ];
    $within = fn (string $name, array $prefixes): bool => array_any($prefixes, fn (string $prefix): bool => str_starts_with($name, "$prefix\\"));
    $isModel = fn (string $name): bool => str_starts_with($name, 'App\\Models\\') || str_contains($name, '\\Models\\');

    $forbidden = [...$wiring, ...array_merge(...array_map($consumer, $modules))];
    $denied = array_merge(
        array_fill_keys($kernel, [...$modules, ...$forbidden]),
        array_combine($modules, array_map(fn (int $i): array => [...array_slice($modules, $i + 1), ...$forbidden], array_keys($modules))),
    );

    $root = dirname(__DIR__, 2).'/app/';
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $class = 'App\\'.str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
        $namespace = implode('\\', array_slice(explode('\\', $class), 0, 2));

        if (! isset($denied[$namespace]) || $within($class, $consumer($namespace))) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        preg_match_all('/^use ([\w\\\\]+)(?: as (\w+))?;/m', $source, $imports, PREG_SET_ORDER);
        $aliases = [];
        foreach ($imports as $import) {
            $aliases[($import[2] ?? '') ?: substr(strrchr('\\'.$import[1], '\\'), 1)] = $import[1];
        }

        foreach (PhpToken::tokenize($source) as $token) {
            $name = ltrim($token->text, '\\');
            $head = strstr($name, '\\', true);

            if ($head !== false && isset($aliases[$head])) {
                $name = $aliases[$head].substr($name, strlen($head));
            }

            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
                && ! ($isModel($class) && $isModel($name))
                && $within($name, $denied[$namespace])) {
                $found[$class][] = $name;
            }
        }
    }

    return $found;
};

it('keeps every module to the kernel and the modules to its left', function () use ($violations, $baseline) {
    expect(array_diff_key($violations(), array_flip($baseline)))->toBe([]);
});

it('lists only live violations in the baseline', function () use ($violations, $baseline) {
    expect(array_values(array_diff($baseline, array_keys($violations()))))->toBe([]);
});
