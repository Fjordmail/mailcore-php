<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;

/**
 * Live contract tests for /reports. Read-only — runs with just an API key
 * (no MAILCORE_CONTRACT_WRITE needed).
 */
#[Group('contract')]
final class ReportsContractTest extends ContractTestCase
{
    public function testSuspiciousMailboxActivityReportIsTyped(): void
    {
        $report = $this->client->reports()->suspiciousMailboxActivity();

        self::assertGreaterThanOrEqual(0, $report->days);
        self::assertGreaterThanOrEqual(0, $report->minAsns);
    }

    public function testReportResponseHasExactlyTheDocumentedKeys(): void
    {
        // Raw shape check (200): the SDK's DTO would default a missing key, so
        // assert the exact key set at every level — top level, a hit, and an ASN.
        $raw = $this->rawJson('/reports/suspicious_mailbox_activity');
        self::assertIsArray($raw);

        self::assertEqualsCanonicalizing(
            ['scanned_at', 'days', 'min_asns', 'skip_flags', 'hits'],
            array_keys($raw),
            'report top-level keys differ from the expected set',
        );

        self::assertIsArray($raw['hits']);
        if ($raw['hits'] === []) {
            self::markTestIncomplete('No suspicious-activity hits available to verify the nested shape.');
        }

        $hit = $raw['hits'][0];
        self::assertEqualsCanonicalizing(
            ['email', 'n_asn', 'n_countries', 'n_ips', 'countries', 'asns'],
            array_keys($hit),
            'report hit keys differ from the expected set',
        );

        self::assertIsArray($hit['asns']);
        if ($hit['asns'] !== []) {
            self::assertEqualsCanonicalizing(
                ['asn', 'name', 'country'],
                array_keys($hit['asns'][0]),
                'report ASN keys differ from the expected set',
            );
        }
    }

    public function testInvalidMailboxplanIdIsRejectedWith417(): void
    {
        // The typed suspiciousMailboxActivity(?int) can't send a malformed id, so
        // hit the endpoint raw to confirm the documented 417 "Invalid mailboxplan_id syntax".
        self::assertSame(417, $this->rawStatus('/reports/suspicious_mailbox_activity', [
            'mailboxplan_id' => 'not-a-number',
        ]));
    }
}
