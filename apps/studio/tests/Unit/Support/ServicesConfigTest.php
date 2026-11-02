<?php

/**
 * Coolify's SERVICE_FQDN_* can hand config/services.php a bare host with no
 * scheme once an app carries more than one domain — RFC 9728's protected-
 * resource document then ships unusable authorization_servers/resource URLs.
 */
$setEnv = function (string $key, ?string $value) {
    if ($value === null) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    } else {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
};

it('forces a scheme onto api/admin/mcp/gallery urls even when the env value has none', function () use ($setEnv) {
    $setEnv('API_URL', 'api.studio.test');
    $setEnv('ADMIN_URL', 'admin.studio.test');
    $setEnv('GALLERY_URL', 'gallery.studio.test');
    $setEnv('MCP_URL', null);

    try {
        $services = require __DIR__.'/../../../config/services.php';
    } finally {
        $setEnv('API_URL', null);
        $setEnv('ADMIN_URL', null);
        $setEnv('GALLERY_URL', null);
    }

    expect($services['api']['url'])->toBe('https://api.studio.test');
    expect($services['admin']['url'])->toBe('https://admin.studio.test');
    expect($services['mcp']['url'])->toBe('https://api.studio.test/mcp');
    expect($services['gallery']['url'])->toBe('https://gallery.studio.test');
});

it('leaves an already-scheme urls untouched', function () use ($setEnv) {
    $setEnv('API_URL', 'http://api.studio.test');
    $setEnv('ADMIN_URL', 'https://admin.studio.test');
    $setEnv('GALLERY_URL', 'http://localhost:4321');
    $setEnv('MCP_URL', null);

    try {
        $services = require __DIR__.'/../../../config/services.php';
    } finally {
        $setEnv('API_URL', null);
        $setEnv('ADMIN_URL', null);
        $setEnv('GALLERY_URL', null);
    }

    expect($services['api']['url'])->toBe('http://api.studio.test');
    expect($services['admin']['url'])->toBe('https://admin.studio.test');
    expect($services['gallery']['url'])->toBe('http://localhost:4321');
});

it('falls back to default gallery url when GALLERY_URL is empty', function () use ($setEnv) {
    $setEnv('GALLERY_URL', '');

    try {
        $services = require __DIR__.'/../../../config/services.php';
    } finally {
        $setEnv('GALLERY_URL', null);
    }

    expect($services['gallery']['url'])->toBe('http://localhost:4321');
});

it('forces a scheme onto the APP_URL fallback too', function () use ($setEnv) {
    $setEnv('API_URL', null);
    $setEnv('ADMIN_URL', null);
    $setEnv('MCP_URL', null);
    $setEnv('APP_URL', 'studio.test');

    try {
        $services = require __DIR__.'/../../../config/services.php';
    } finally {
        $setEnv('APP_URL', null);
    }

    expect($services['api']['url'])->toBe('https://studio.test');
    expect($services['admin']['url'])->toBe('https://studio.test');
    expect($services['mcp']['url'])->toBe('https://studio.test/mcp');
});
