<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Resource;

use Inboxcom\Mailcore\Model\Mailboxplan;
use Inboxcom\Mailcore\Tests\MailcoreTestCase;

/** Covers the smaller resources: mailboxplans, reports, datadump. */
final class OtherResourcesTest extends MailcoreTestCase
{
    public function testMailboxplansListMapsModelsWithBooleans(): void
    {
        $client = $this->client(self::json([
            ['id' => 4, 'name' => 'Demo Plan', 'mailbox_quota' => 15360, 'imap' => 1, 'pop3' => 1, 'smtp' => 1, 'webmail' => 0, 'aliases' => 5, 'forwards' => 0, 'date_created' => '2014-02-13 13:06:57'],
        ]));

        $plans = $client->mailboxplans()->list();

        self::assertContainsOnlyInstancesOf(Mailboxplan::class, $plans);
        self::assertSame('Demo Plan', $plans[0]->name);
        self::assertTrue($plans[0]->smtp);
        self::assertFalse($plans[0]->webmail);
        self::assertSame('/mailboxplans/list', $this->http->lastPath());
    }

    public function testReportsSuspiciousActivityDecodesNestedTree(): void
    {
        $client = $this->client(self::json([
            'scanned_at' => '2026-06-01T16:10:26+02:00',
            'days' => 14,
            'min_asns' => 5,
            'skip_flags' => ['spammer'],
            'hits' => [
                [
                    'email' => 'user1.demo.test@example.net', 'n_asn' => 2, 'n_countries' => 2, 'n_ips' => 7,
                    'countries' => ['FI', 'US'],
                    'asns' => [
                        ['asn' => 64500, 'name' => 'EXAMPLE-AS ONE', 'country' => 'FI'],
                        ['asn' => 64501, 'name' => 'EXAMPLE-AS TWO', 'country' => 'US'],
                    ],
                ],
            ],
        ]));

        $report = $client->reports()->suspiciousMailboxActivity(28);

        self::assertSame('/reports/suspicious_mailbox_activity', $this->http->lastPath());
        self::assertSame(['mailboxplan_id' => '28'], $this->http->lastQuery());
        self::assertSame(14, $report->days);
        self::assertSame(['spammer'], $report->skipFlags);
        self::assertCount(1, $report->hits);
        self::assertSame('user1.demo.test@example.net', $report->hits[0]->email);
        self::assertCount(2, $report->hits[0]->asns);
        self::assertSame(64500, $report->hits[0]->asns[0]->asn);
        self::assertSame('FI', $report->hits[0]->asns[0]->country);
    }

    public function testDatadumpReturnsRawBytes(): void
    {
        $binary = "\x89BINARYPGP\x00data";
        $client = $this->client(new \GuzzleHttp\Psr7\Response(200, [], $binary));

        self::assertSame($binary, $client->datadump()->fetchLatest());
        self::assertSame('/datadump/fetch_latest', $this->http->lastPath());
    }
}
