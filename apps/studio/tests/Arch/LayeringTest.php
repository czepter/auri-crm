<?php

// Order and rules: milestone architecture-rework, #555.
// A module may use the kernel and the modules to its left. Http, Console, Mcp
// and Screens are consumer code: they may use anything, nothing may use them.
// Models may use models of any module, for Eloquent relations.

$kernel = ['App\Concerns', 'App\Support', 'App\Enums', 'App\Models', 'App\Data', 'App\Jobs', 'App\Notifications'];

$modules = [
    'App\Numbering', 'App\Documents', 'App\Media', 'App\Crm',
    'App\Catalog', 'App\Engagement', 'App\Portal', 'App\Quotes', 'App\Contracts', 'App\Payments',
    'App\Gallery', 'App\Automation', 'App\Settings', 'App\Reporting', 'App\Finance', 'App\Privacy',
    'App\Integration', 'App\Help', 'App\Releases', 'App\Staff',
];

$wiring = ['App\Http', 'App\Policies', 'App\Providers', 'App\Templates', 'App\Migration'];

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

it('keeps every module to the kernel and the modules to its left', function () use ($violations) {
    expect($violations())->toBe([]);
});
