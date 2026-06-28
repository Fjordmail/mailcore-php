<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Resource;

use Inboxcom\Mailcore\Resource\Mailfilter;
use Inboxcom\Mailcore\Model\SmtpLimitHit;
use Inboxcom\Mailcore\Model\SpamFlag;
use Inboxcom\Mailcore\Tests\MailcoreTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MailfilterTest extends MailcoreTestCase
{
    /** @return iterable<string, array{\Closure(Mailfilter): void, string, array<string, string>}> */
    public static function actionProvider(): iterable
    {
        yield 'whitelistSender' => [
            static fn (Mailfilter $m) => $m->whitelistSender('me.demo.test@example.com', 'friend@x.com'),
            '/mailfilter/whitelistsender',
            ['recipient' => 'me.demo.test@example.com', 'sender' => 'friend@x.com'],
        ];
        yield 'blacklistSender' => [
            static fn (Mailfilter $m) => $m->blacklistSender('me.demo.test@example.com', 'spam@x.com'),
            '/mailfilter/blacklistsender',
            ['recipient' => 'me.demo.test@example.com', 'sender' => 'spam@x.com'],
        ];
        yield 'whitedelistSender' => [
            static fn (Mailfilter $m) => $m->whitedelistSender('me.demo.test@example.com', 'friend@x.com'),
            '/mailfilter/whitedelistsender',
            ['recipient' => 'me.demo.test@example.com', 'sender' => 'friend@x.com'],
        ];
        yield 'blackdelistSender' => [
            static fn (Mailfilter $m) => $m->blackdelistSender('me.demo.test@example.com', 'spam@x.com'),
            '/mailfilter/blackdelistsender',
            ['recipient' => 'me.demo.test@example.com', 'sender' => 'spam@x.com'],
        ];
        yield 'clearWhitelist' => [
            static fn (Mailfilter $m) => $m->clearWhitelist('me.demo.test@example.com'),
            '/mailfilter/clearwhitelist',
            ['recipient' => 'me.demo.test@example.com'],
        ];
        yield 'clearBlacklist' => [
            static fn (Mailfilter $m) => $m->clearBlacklist('me.demo.test@example.com'),
            '/mailfilter/clearblacklist',
            ['recipient' => 'me.demo.test@example.com'],
        ];
    }

    /**
     * @param \Closure(Mailfilter): void $call
     * @param array<string, string>      $expectedQuery
     */
    #[DataProvider('actionProvider')]
    public function testActionSendsExpectedRequest(\Closure $call, string $path, array $expectedQuery): void
    {
        $client = $this->client(self::empty());
        $call($client->mailfilter());

        self::assertSame($path, $this->http->lastPath());
        self::assertSame($expectedQuery, $this->http->lastQuery());
    }

    public function testSenderMethodsAcceptDomainWildcard(): void
    {
        $client = $this->client(self::empty(201));

        $client->mailfilter()->whitelistSender('*@example.test', '*@spam.test');

        self::assertSame(['recipient' => '*@example.test', 'sender' => '*@spam.test'], $this->http->lastQuery());
    }

    public function testSenderMethodsRejectMalformedValue(): void
    {
        $client = $this->client(self::empty());

        $this->expectException(\InvalidArgumentException::class);
        $client->mailfilter()->blacklistSender('not-an-address', 'spam@x.com');
    }

    public function testLatestSmtpLimitHitsMapsModels(): void
    {
        $client = $this->client(self::json([
            ['email' => 'a.demo.test@example.com', 'ip' => '192.168.1.1', 'last_hit' => '2025-03-11 14:40:01'],
        ]));

        $hits = $client->mailfilter()->latestSmtpLimitHits(4);

        self::assertContainsOnlyInstancesOf(SmtpLimitHit::class, $hits);
        self::assertSame('a.demo.test@example.com', $hits[0]->email);
        self::assertSame(['mailboxplan_id' => '4'], $this->http->lastQuery());
    }

    public function testLatestSpamFlagsMapsModels(): void
    {
        $client = $this->client(self::json([
            ['email' => 'a.demo.test@example.com', 'flag' => 'spammer', 'date_set' => '2025-03-11 14:40:01'],
        ]));

        $flags = $client->mailfilter()->latestSpamFlags();

        self::assertContainsOnlyInstancesOf(SpamFlag::class, $flags);
        self::assertSame('spammer', $flags[0]->flag);
    }

    public function testListWhitelistReturnsEntries(): void
    {
        $client = $this->client(self::json(['no-reply@google.com', 'notifications@facebook.com']));

        self::assertSame(['no-reply@google.com', 'notifications@facebook.com'], $client->mailfilter()->listWhitelist('me.demo.test@example.com'));
    }

    public function testListWhitelistReturnsEmptyArrayWhenNoMatchingEntries(): void
    {
        $client = $this->client(self::error(417, 'No matching entries found'));

        self::assertSame([], $client->mailfilter()->listWhitelist('me.demo.test@example.com'));
    }

    public function testListBlacklistReturnsEmptyArrayWhenNoMatchingEntries(): void
    {
        $client = $this->client(self::error(417, 'No matching entries found'));

        self::assertSame([], $client->mailfilter()->listBlacklist('me.demo.test@example.com'));
    }

    public function testRblLookupListedAndClean(): void
    {
        self::assertTrue($this->client(self::error(409, '[ip] was found listed on RBL lists'))->mailfilter()->isListedOnRbl('8.8.8.8'));
        self::assertFalse($this->client(self::empty(200))->mailfilter()->isListedOnRbl('8.8.8.8'));
    }

    public function testRblLookupRethrowsOnInvalidIp(): void
    {
        $this->expectException(\Inboxcom\Mailcore\Exception\BadRequestException::class);
        $this->client(self::error(400, 'IPv4 address not valid'))->mailfilter()->isListedOnRbl('nope');
    }

    public function testCdlLookupListedAndClean(): void
    {
        self::assertTrue($this->client(self::error(409, '[ip] was found listed on CDL'))->mailfilter()->isListedOnCdl('8.8.8.8'));
        self::assertFalse($this->client(self::empty(200))->mailfilter()->isListedOnCdl('8.8.8.8'));
    }

    public function testCdlLookupRethrowsOnInvalidIp(): void
    {
        $this->expectException(\Inboxcom\Mailcore\Exception\BadRequestException::class);
        $this->client(self::error(400, 'IPv4 address not valid'))->mailfilter()->isListedOnCdl('nope');
    }
}
