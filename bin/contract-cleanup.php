#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Authoritative cleanup for the live contract suite.
 *
 * The Mailcore API deletes mailboxes asynchronously and slowly, and a domain
 * cannot be removed until every related mailbox is fully gone — so the suite's
 * in-process teardown can time out and leave the throwaway test domain behind.
 * Run this after a write run if anything lingers:
 *
 *   composer contract:cleanup            # from packages/mailcore-php
 *   php bin/contract-cleanup.php
 *
 * It discovers every contract domain currently present (the host plus any
 * derived crud-/alias- variant) by listing domains that end with our test suffix,
 * then for each enumerates the test mailboxes on the domain (via the `*@domain`
 * list filter, constrained to the test mailbox plan so only our own fixtures are
 * ever touched), removes them, then patiently removes the domain. A domain can't
 * be removed until the background job has purged its mailboxes, so removal retries
 * for several minutes.
 * Safe to run repeatedly; uses the same key resolution as the suite
 * (MAILCORE_API_KEY or ~/.config/mailcore/config.ini).
 */

require __DIR__ . '/../vendor/autoload.php';

use Inboxcom\Mailcore\MailcoreClient;
use Inboxcom\Mailcore\Tests\Contract\ContractTestCase;

$cfgPath = (getenv('XDG_CONFIG_HOME') ?: (getenv('HOME') . '/.config')) . '/mailcore/config.ini';
$ini = is_file($cfgPath) ? (parse_ini_file($cfgPath, false, INI_SCANNER_NORMAL) ?: []) : [];
$apiKey = getenv('MAILCORE_API_KEY') ?: ($ini['api_key'] ?? '');
if ($apiKey === '') {
    fwrite(STDERR, "No API key (set MAILCORE_API_KEY or ~/.config/mailcore/config.ini).\n");
    exit(1);
}
$client = new MailcoreClient($apiKey, getenv('MAILCORE_BASE_URI') ?: ($ini['base_uri'] ?? MailcoreClient::DEFAULT_BASE_URI));

$host = ContractTestCase::TEST_DOMAIN;
$planId = ContractTestCase::mailboxPlanId();

$exists = static function (MailcoreClient $client, string $domain): bool {
    try {
        $client->domains()->list(domain: $domain);

        return true;
    } catch (\Throwable) {
        return false;
    }
};

/**
 * Remove every test mailbox on $domain: enumerated via the `*@domain` filter and
 * constrained to the test mailbox plan, so we only ever delete our own fixtures.
 */
$purgeMailboxes = static function (MailcoreClient $client, string $domain) use ($planId): void {
    try {
        $all = $client->users()->list(filter: '*@' . $domain, limit: '0,1000', mailboxplanId: $planId);
    } catch (\Throwable $e) {
        echo "  could not list mailboxes on {$domain}: {$e->getMessage()}\n";

        return;
    }

    // Defensive: only touch addresses actually on this domain.
    $onDomain = array_values(array_filter($all, static fn (string $e): bool => str_ends_with($e, '@' . $domain)));
    if ($onDomain === []) {
        return;
    }

    echo '  removing ' . count($onDomain) . " mailbox(es) on {$domain} ...\n";
    foreach ($onDomain as $email) {
        try {
            $client->users()->remove($email);
            echo "    removed {$email}\n";
        } catch (\Throwable $e) {
            echo "    remove {$email}: {$e->getMessage()}\n";
        }
    }
};

$maxSeconds = 600;

// Discover every contract domain currently present — the host plus any prefixed
// variant (crud-/alias-/...) — via a server-side suffix filter, rather than a
// hardcoded list. The trailing-anchored "*<host>" matches anything ending with it.
try {
    $domains = $client->domains()->list(filter: '*' . $host, limit: '0,1000');
} catch (\Throwable $e) {
    fwrite(STDERR, "Could not list domains: {$e->getMessage()}\n");
    exit(1);
}
// Defensive: keep only domains that genuinely end with our suffix, and remove the
// host itself LAST (the prefixed variants merely share its suffix).
$domains = array_values(array_filter($domains, static fn (string $d): bool => str_ends_with($d, $host)));
usort($domains, static fn (string $a, string $b): int => (int) ($a === $host) <=> (int) ($b === $host));

if ($domains === []) {
    echo "No contract domains (suffix {$host}) present — nothing to do.\n";
    exit(0);
}
echo 'Found ' . count($domains) . ' contract domain(s): ' . implode(', ', $domains) . "\n";

foreach ($domains as $domain) {
    if (! $exists($client, $domain)) {
        echo "{$domain}: already gone\n";
        continue;
    }
    // Flag every mailbox on the domain for deletion first — the domain can't be
    // removed until they are gone.
    $purgeMailboxes($client, $domain);
    echo "Removing {$domain} (mailbox deletion is slow; retrying up to {$maxSeconds}s) ...\n";
    $waited = 0;
    while ($waited < $maxSeconds) {
        try {
            $client->domains()->remove($domain);
        } catch (\Throwable) {
            // 406 while related mailboxes finish deleting
        }
        if (! $exists($client, $domain)) {
            echo "  gone after ~{$waited}s\n";
            break;
        }
        sleep(5);
        $waited += 5;
    }
    if ($exists($client, $domain)) {
        echo "  STILL PRESENT after {$maxSeconds}s — re-run later (mailboxes may still be deleting)\n";
    }
}

echo "\nFinal state:\n";
foreach ($domains as $domain) {
    echo '  ' . ($exists($client, $domain) ? "PRESENT: {$domain}" : "clean: {$domain}") . "\n";
}
