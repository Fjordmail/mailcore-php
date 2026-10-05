# inboxcom/mailcore-php

[![Latest Version](https://img.shields.io/packagist/v/inboxcom/mailcore-php.svg)](https://packagist.org/packages/inboxcom/mailcore-php)
[![License](https://img.shields.io/packagist/l/inboxcom/mailcore-php.svg)](LICENSE)

PHP client for the **Mailcore** mail-server control API (users, mailboxes, domains, filters, reports).

> **Unofficial.** Third-party client; not published by Mailcore.

> **AI-assisted.** Code, tests, and docs were written largely by AI (Claude Code) under human direction and review. Review it before relying on it in production.

This is the **core** package — a PSR-18 client with **no framework dependency**. For the command-line tool see [`inboxcom/mailcore-cli`](https://packagist.org/packages/inboxcom/mailcore-cli); for the Laravel bridge see [`inboxcom/mailcore-laravel`](https://packagist.org/packages/inboxcom/mailcore-laravel).

## Install

```bash
composer require inboxcom/mailcore-php
```

Requires PHP >= 8.4. Guzzle is used by default (it is itself a PSR-18 client), but you can inject any PSR-18 client + PSR-17 request factory.

The default client uses a **30s request timeout** and **10s connect timeout**; override per client (seconds, `0` disables):

```php
$client = new MailcoreClient('your-api-key', timeout: 60.0, connectTimeout: 5.0);
```

A whole dump from `datadump()->fetch()` can be large — raise `timeout` (or pass `0`) for it. These options apply only to the default Guzzle client; when you inject your own (below), configure timeouts on it.

## Usage

```php
use Inboxcom\Mailcore\MailcoreClient;
use Inboxcom\Mailcore\Exception\ConflictException;

$client = new MailcoreClient('your-api-key');

$emails = $client->users()->list(filter: '*', limit: '0,100');
$user   = $client->users()->get('holger@example.com'); // returns a User DTO

try {
    $client->users()->add('new@example.com', 'S3cretPassw0rd', mailboxplanId: 4);
} catch (ConflictException $e) {
    // $e->statusCode === 409, $e->statusMsg === 'User already exists'
}
```

### Injecting your own HTTP client (e.g. in Roundcube)

The core carries no Console/Laravel dependency, so it drops into any host:

```php
$client = new MailcoreClient(
    apiKey: 'your-api-key',
    httpClient: $yourPsr18Client,
    requestFactory: $yourPsr17RequestFactory,
);
```

### Error handling

Every non-2xx response throws a subclass of `Inboxcom\Mailcore\Exception\ApiException`, chosen by HTTP status (`NotFoundException`, `ConflictException`, `NotAcceptableException`, …). The API's `statusmsg` is preserved on `$e->statusMsg` for finer branching. Catch `MailcoreException` to catch everything this package throws. **The API key is never included in any exception message.**

## Scope

The core covers **all 55 operations** of the live Mailcore API, grouped by tag onto resource accessors:

| Accessor | Operations |
| --- | --- |
| `users()` | 34 — CRUD, aliases (+ list), forwards (+ list), passwords, flags, snapshots/restores, logins, limits, spam tolerance, temporary access |
| `domains()` | 5 — count, add, add alias, list, remove |
| `mailboxplans()` | 1 — list |
| `mailfilter()` | 13 — SMTP-limit/spam-flag feeds, white/blacklists, RBL + CDL + BPL lookups |
| `reports()` | 1 — suspicious mailbox activity |
| `datadump()` | 1 — fetch latest (raw bytes) |

Object responses come back as typed models in `src/Model`; list endpoints return `list<Model>`. A few binary-outcome endpoints are exposed as predicates (`isAvailable()`, `isReserved()`, `verifyPassword()`, `mailfilter()->isListedOnRbl()`, `mailfilter()->isListedOnCdl()`, `mailfilter()->isListedOnBpl()`). For richer detail, `mailfilter()->rblLookup()` returns an `RblResult` naming which lists flag the IP, and `mailfilter()->bplLookup()` returns a `BplListing` (sample usernames + abuse timeframe) when the host is blocked (or `null` when clean).

## OpenAPI

The OpenAPI document is **generated from `#[OA\*]` attributes** on the resource methods plus the holders in `src/Doc`, so the spec can't drift from the client. swagger-php is a dev-only dependency and the attributes are inert at runtime.

```bash
composer gen:openapi            # write build/openapi.yaml
composer openapi:check          # generate + validate only (CI gate)
composer docs:swagger           # serve Swagger UI at http://localhost:8081/ (PHP only)
```

## Development & full docs

This package is developed in the [`Fjordmail/mailcore-sdk`](https://github.com/Fjordmail/mailcore-sdk) monorepo (this repository is a read-only subtree split). Issues, contributions, the live contract suite, and the full compatibility notes against the official v1.23 docs all live there.

## License

MIT — see [LICENSE](LICENSE).
