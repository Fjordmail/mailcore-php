#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate the OpenAPI document from the #[OA\*] attributes in src/.
 *
 * Usage:
 *   php bin/generate-openapi.php [output-path]   Write the spec (default: build/openapi.yaml)
 *   php bin/generate-openapi.php --check         Generate + validate only; no file written (CI)
 *
 * The `servers` URL is resolved from MAILCORE_BASE_URI, else `base_uri` in
 * ~/.config/mailcore/config.ini; otherwise the committed placeholder host is used.
 *
 * The attributes across src/ (resources + src/Doc) are the single source of
 * truth. The generated spec is a build artifact (build/ is gitignored); the
 * curated source material lives under assets/.
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;

$check = in_array('--check', $argv, true);
$paths = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => ! str_starts_with($a, '--')));
$out = $paths[0] ?? __DIR__ . '/../build/openapi.yaml';

$openapi = (new Generator())->generate([__DIR__ . '/../src'], validate: false);
$openapi->openapi = '3.1.1';

// The committed `#[OA\Server]` URL is a placeholder host. Resolve the real
// endpoint the way the client/CLI do — MAILCORE_BASE_URI, else the `base_uri`
// from ~/.config/mailcore/config.ini — and emit it as the `servers` URL (the
// `/{apiKey}` path segment is preserved). Nothing real is baked into the source.
$cfgPath = (getenv('XDG_CONFIG_HOME') ?: (getenv('HOME') . '/.config')) . '/mailcore/config.ini';
$ini = is_file($cfgPath) ? (parse_ini_file($cfgPath, false, INI_SCANNER_NORMAL) ?: []) : [];
$baseUri = getenv('MAILCORE_BASE_URI') ?: ($ini['base_uri'] ?? '');
if (is_string($baseUri) && $baseUri !== '' && is_array($openapi->servers) && isset($openapi->servers[0])) {
    $openapi->servers[0]->url = rtrim($baseUri, '/') . '/{apiKey}';
}

if (! $openapi->validate()) {
    fwrite(STDERR, "OpenAPI spec failed validation (see messages above).\n");
    exit(1);
}

if ($check) {
    fwrite(STDERR, "OpenAPI spec generates and validates.\n");
    exit(0);
}

if (! is_dir(dirname($out)) && ! mkdir($dir = dirname($out), 0777, true) && ! is_dir($dir)) {
    fwrite(STDERR, "Could not create output directory.\n");
    exit(1);
}

file_put_contents($out, $openapi->toYaml());
fwrite(STDERR, sprintf("Wrote %s\n", realpath($out) ?: $out));
