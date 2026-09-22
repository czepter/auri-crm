<?php

/**
 * Coolify's SERVICE_FQDN_* can hand config/services.php a bare host with no
 * scheme once an app carries more than one domain — RFC 9728's protected-
 * resource document then ships unusable authorization_servers/resource URLs.
 */
it('forces a scheme onto api/admin/mcp urls even when the env value has none', function () {
    putenv('API_URL=api.studio.test');
    putenv('ADMIN_URL=admin.studio.test');
    putenv('MCP_URL');

    try {
        $services = require __DIR__.'/../../../config/services.php';
    } finally {
        putenv('API_URL');
        putenv('ADMIN_URL');
    }

    expect($services['api']['url'])->toBe('https://api.studio.test');
    expect($services['admin']['url'])->toBe('https://admin.studio.test');
    expect($services['mcp']['url'])->toBe('https://api.studio.test/mcp');
});

it('leaves an already-scheme urls untouched', function () {
    putenv('API_URL=http://api.studio.test');
    putenv('ADMIN_URL=https://admin.studio.test');
    putenv('MCP_URL');

    try {
        $services = require __DIR__.'/../../../config/services.php';
    } finally {
        putenv('API_URL');
        putenv('ADMIN_URL');
    }

    expect($services['api']['url'])->toBe('http://api.studio.test');
    expect($services['admin']['url'])->toBe('https://admin.studio.test');
});
