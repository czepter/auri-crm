<?php

/**
 * Checks docker-compose.coolify.yml against the rules Coolify's compose parser
 * enforces but never reports (1-4), and the one this app enforces on itself
 * (5). Every one of them fails silently: the stack deploys, the containers
 * start, and a variable is empty, a domain points at the wrong container, or a
 * mail carries the wrong host. Run by .github/workflows/release-image.yml
 * before the image is built.
 *
 * Deliberately a text scan rather than a YAML parse: rule 3 is about the order
 * keys appear in, and every parser — including `docker compose config`, which
 * sorts them alphabetically — throws that away.
 */

$path = $argv[1] ?? dirname(__DIR__, 2).'/docker-compose.coolify.yml';
$lines = file($path, FILE_IGNORE_NEW_LINES);
$errors = [];

$service = null;
$declaredOwnFqdn = [];
$referencedFqdn = [];
$surface = [];
$appUrl = [];

foreach ($lines as $i => $line) {
    $no = $i + 1;

    if (str_starts_with(ltrim($line), '#')) {
        continue;
    }

    // Rule 1: Coolify cannot parse anchors or merge keys (coollabsio/coolify#2705).
    if (preg_match('/^\s*[\w-]+:\s*&\w/', $line) || preg_match('/^\s*<<:\s*\*/', $line)) {
        $errors[] = "$no: YAML anchor or merge key — Coolify fails to parse the file.";
    }

    if (preg_match('/^  ([\w-]+):\s*$/', $line, $m) && $m[1] !== 'volumes' && $m[1] !== 'networks') {
        $service = $m[1];

        continue;
    }

    if ($service === null) {
        continue;
    }

    // A keyed SERVICE_FQDN_<NAME>_<PORT> is what registers this service's own
    // domain with the proxy, and it is what makes a later reference safe.
    if (preg_match('/^\s+SERVICE_FQDN_[A-Z0-9]+_\d+:\s*$/', $line)) {
        $declaredOwnFqdn[$service] = true;

        continue;
    }

    if (! preg_match('/^\s+([A-Z_][A-Z0-9_]*):\s*(\S.*)$/', $line, $m)) {
        continue;
    }

    [, $key, $value] = $m;
    $value = trim($value);

    if ($key === 'APP_SURFACE') {
        $surface[$service] = $value;
    }

    if ($key === 'APP_URL') {
        $appUrl[$service] = [$no, $value];
    }

    // Rule 2: a SERVICE_* variable is only generated when it is the whole value.
    // shared.php gates on `$value?->startsWith('$')`, so a prefix, a suffix or a
    // second reference in the same value leaves it ungenerated and empty.
    if (str_contains($value, 'SERVICE_') && ! preg_match('/^\$\{?SERVICE_[A-Z0-9_]+\}?$/', $value)) {
        $errors[] = "$no: $service.$key = $value — a SERVICE_* variable has to be the entire value or Coolify skips it.";
    }

    // Rule 3/4: shared.php assigns a referenced domain to the service the
    // reference is written in, but only when that service has no domain yet
    // *and* nothing has referenced that bare name before
    // (`is_null($savedService->fqdn) && ! $foundEnv`). The keyed form stores
    // SERVICE_FQDN_<NAME>_<PORT>, a different key, so only an earlier
    // reference sets $foundEnv. Either condition failing makes the reference
    // safe; both holding is what silently moves a domain.
    if (preg_match('/^\$\{?(SERVICE_FQDN_[A-Z0-9]+)\}?$/', $value, $ref)) {
        if (! ($declaredOwnFqdn[$service] ?? false) && ! isset($referencedFqdn[$ref[1]])) {
            $errors[] = "$no: $service.$key is the first reference to \${$ref[1]} and $service declares no domain of its own — Coolify would hand that domain to $service.";
        }

        $referencedFqdn[$ref[1]] = true;
    }
}

// Rule 5: a container composes links for the surface it boots (App\Support\Surface),
// so its APP_URL is that surface's own domain — links onto another surface name
// their host separately (PORTAL_URL, GALLERY_URL). Pointing APP_URL at a foreign
// surface fails as silently as the rest: the mail sends, and the link in it is
// simply the wrong host.
foreach ($appUrl as $name => [$no, $value]) {
    if (! isset($surface[$name])) {
        continue;
    }

    $expected = '${SERVICE_FQDN_'.strtoupper($surface[$name]).'}';

    if ($value !== $expected) {
        $errors[] = "$no: $name.APP_URL = $value but APP_SURFACE is {$surface[$name]} — expected $expected.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "docker-compose.coolify.yml breaks rules that fail silently at deploy:\n\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  $error\n");
    }
    fwrite(STDERR, "\n");
    exit(1);
}

echo "docker-compose.coolify.yml: Coolify parser rules OK\n";
