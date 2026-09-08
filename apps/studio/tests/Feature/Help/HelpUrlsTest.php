<?php

it('lässt API_URL und MCP_URL vor dem APP_URL-Fallback gewinnen', function () {
    putenv('API_URL=https://api.distinctive.test/');
    putenv('MCP_URL=https://mcp.distinctive.test/');
    $_ENV['API_URL'] = 'https://api.distinctive.test/';
    $_ENV['MCP_URL'] = 'https://mcp.distinctive.test/';

    try {
        $config = require config_path('services.php');
    } finally {
        putenv('API_URL');
        putenv('MCP_URL');
        unset($_ENV['API_URL'], $_ENV['MCP_URL']);
    }

    expect($config['api']['url'])->toBe('https://api.distinctive.test')
        ->and($config['mcp']['url'])->toBe('https://mcp.distinctive.test');
});
