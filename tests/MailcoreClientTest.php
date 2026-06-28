<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests;

use Inboxcom\Mailcore\MailcoreClient;
use Inboxcom\Mailcore\Resource\Datadump;
use Inboxcom\Mailcore\Resource\Domains;
use Inboxcom\Mailcore\Resource\Mailboxplans;
use Inboxcom\Mailcore\Resource\Mailfilter;
use Inboxcom\Mailcore\Resource\Reports;
use Inboxcom\Mailcore\Resource\Users;
use PHPUnit\Framework\TestCase;

final class MailcoreClientTest extends TestCase
{
    public function testEmptyApiKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailcoreClient('   ');
    }

    public function testResourceAccessorsReturnExpectedTypes(): void
    {
        $client = new MailcoreClient('key');

        self::assertInstanceOf(Users::class, $client->users());
        self::assertInstanceOf(Domains::class, $client->domains());
        self::assertInstanceOf(Mailboxplans::class, $client->mailboxplans());
        self::assertInstanceOf(Mailfilter::class, $client->mailfilter());
        self::assertInstanceOf(Reports::class, $client->reports());
        self::assertInstanceOf(Datadump::class, $client->datadump());
    }

    public function testResourceAccessorsAreMemoised(): void
    {
        $client = new MailcoreClient('key');

        self::assertSame($client->users(), $client->users());
        self::assertSame($client->mailfilter(), $client->mailfilter());
    }
}
