<?php

/** Setzt den Schluessel in allen drei Quellen, die Env liest — $_SERVER zuerst. */
function withUrlEnv(array $values, callable $fn): mixed
{
    foreach ($values as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    try {
        return $fn();
    } finally {
        foreach (array_keys($values) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}

it('lässt API_URL und MCP_URL vor dem APP_URL-Fallback gewinnen', function () {
    $config = withUrlEnv([
        'API_URL' => 'https://api.distinctive.test/',
        'MCP_URL' => 'https://mcp.distinctive.test/',
    ], fn () => require config_path('services.php'));

    expect($config['api']['url'])->toBe('https://api.distinctive.test')
        ->and($config['mcp']['url'])->toBe('https://mcp.distinctive.test');
});

it('nimmt APP_URL, wenn die Schlüssel leer gesetzt sind', function () {
    $config = withUrlEnv([
        'API_URL' => '',
        'MCP_URL' => '',
        'APP_URL' => 'https://studio.distinctive.test',
    ], fn () => require config_path('services.php'));

    expect($config['api']['url'])->toBe('https://studio.distinctive.test')
        ->and($config['mcp']['url'])->toBe('https://studio.distinctive.test');
});
