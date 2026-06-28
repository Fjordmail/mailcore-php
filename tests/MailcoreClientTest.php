<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests;

use GuzzleHttp\Client as GuzzleClient;
use Inboxcom\Mailcore\Http\Transport;
use Inboxcom\Mailcore\MailcoreClient;
use Inboxcom\Mailcore\Resource\Datadump;
use Inboxcom\Mailcore\Resource\Domains;
use Inboxcom\Mailcore\Resource\Mailboxplans;
use Inboxcom\Mailcore\Resource\Mailfilter;
use Inboxcom\Mailcore\Resource\Reports;
use Inboxcom\Mailcore\Resource\Users;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

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

    public function testDefaultClientUsesTheDefaultTimeouts(): void
    {
        $config = self::guzzleConfig(new MailcoreClient('key'));

        self::assertSame(MailcoreClient::DEFAULT_TIMEOUT, $config['timeout']);
        self::assertSame(MailcoreClient::DEFAULT_CONNECT_TIMEOUT, $config['connect_timeout']);
    }

    public function testTimeoutsAreConfigurable(): void
    {
        $config = self::guzzleConfig(new MailcoreClient('key', timeout: 12.5, connectTimeout: 3.0));

        self::assertSame(12.5, $config['timeout']);
        self::assertSame(3.0, $config['connect_timeout']);
    }

    public function testInjectedHttpClientIsUsedVerbatimAndTimeoutsAreIgnored(): void
    {
        $injected = new class implements ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \LogicException('not called');
            }
        };
        $client = new MailcoreClient('key', httpClient: $injected, timeout: 1.0, connectTimeout: 1.0);

        self::assertSame($injected, self::httpClientOf($client));
    }

    /** @return array<string, mixed> The default Guzzle client's merged config. */
    private static function guzzleConfig(MailcoreClient $client): array
    {
        $http = self::httpClientOf($client);
        self::assertInstanceOf(GuzzleClient::class, $http);

        /** @var array<string, mixed> $config */
        $config = (new \ReflectionProperty(GuzzleClient::class, 'config'))->getValue($http);

        return $config;
    }

    private static function httpClientOf(MailcoreClient $client): ClientInterface
    {
        $transport = (new \ReflectionProperty(MailcoreClient::class, 'transport'))->getValue($client);
        \assert($transport instanceof Transport);

        return (new \ReflectionProperty(Transport::class, 'httpClient'))->getValue($transport);
    }
}
