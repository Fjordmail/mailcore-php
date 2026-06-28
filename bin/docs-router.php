<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in server (`php -S … -t build bin/docs-router.php`).
 *
 *  - Serves a chosen file at `/` (the index is picked via DOCS_INDEX, default
 *    index.html).
 *  - Reverse-proxies `/__proxy/<path>` to the configured Mailcore base URI so
 *    Swagger UI's "Try it out" works despite the live API sending no CORS
 *    headers: the browser calls localhost (same origin) and PHP forwards the
 *    request server-side. It ONLY forwards to that one base host, and binds to
 *    localhost — a local dev convenience, not an open proxy.
 *  - Everything else falls through to the static docroot.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$proxyPrefix = '/__proxy/';
if (str_starts_with($path, $proxyPrefix)) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $cfgPath = (getenv('XDG_CONFIG_HOME') ?: (getenv('HOME') . '/.config')) . '/mailcore/config.ini';
    $ini = is_file($cfgPath) ? (parse_ini_file($cfgPath, false, INI_SCANNER_NORMAL) ?: []) : [];
    $base = rtrim(getenv('MAILCORE_BASE_URI') ?: ($ini['base_uri'] ?? ''), '/');
    if ($base === '') {
        http_response_code(500);
        echo 'Proxy: set MAILCORE_BASE_URI or config.ini base_uri to the real endpoint.';

        return true;
    }

    $target = $base . '/' . substr($path, strlen($proxyPrefix));
    if (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '') {
        $target .= '?' . $qs;
    }

    try {
        $resp = (new \GuzzleHttp\Client(['http_errors' => false, 'timeout' => 30]))->request(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $target,
            ['body' => file_get_contents('php://input') ?: null],
        );
        http_response_code($resp->getStatusCode());
        if (($ct = $resp->getHeaderLine('Content-Type')) !== '') {
            header('Content-Type: ' . $ct);
        }
        echo (string) $resp->getBody();
    } catch (\Throwable $e) {
        http_response_code(502);
        echo 'Proxy error: ' . $e->getMessage();
    }

    return true;
}

if ($path === '/') {
    $file = __DIR__ . '/../build/' . (getenv('DOCS_INDEX') ?: 'index.html');
    if (is_file($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);

        return true;
    }
}

return false; // serve the requested static file from the docroot as usual
