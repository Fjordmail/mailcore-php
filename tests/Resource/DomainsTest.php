<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Resource;

use Inboxcom\Mailcore\Tests\MailcoreTestCase;

final class DomainsTest extends MailcoreTestCase
{
    public function testListReturnsDomains(): void
    {
        $client = $this->client(self::json(['example.com', 'example.net']));

        self::assertSame(['example.com', 'example.net'], $client->domains()->list());
        self::assertSame('/domains/list', $this->http->lastPath());
    }

    public function testListForwardsFilters(): void
    {
        $client = $this->client(self::json([]));
        $client->domains()->list(domain: 'example.com', limit: '0,100', filter: '*');

        self::assertSame(['domain' => 'example.com', 'limit' => '0,100', 'filter' => '*'], $this->http->lastQuery());
    }

    public function testCountReturnsInt(): void
    {
        $client = $this->client(self::json(4513));

        self::assertSame(4513, $client->domains()->count());
        self::assertSame('/domains/countdomains', $this->http->lastPath());
    }

    public function testAddSendsDomain(): void
    {
        $client = $this->client(self::empty(201));
        $client->domains()->add('example-test.com');

        self::assertSame('/domains/add', $this->http->lastPath());
        self::assertSame(['domain' => 'example-test.com'], $this->http->lastQuery());
    }

    public function testAddAliasSendsBothDomains(): void
    {
        $client = $this->client(self::empty(201));
        $client->domains()->addAlias('example-test.com', 'alias-test.example');

        self::assertSame(['domain' => 'example-test.com', 'alias' => 'alias-test.example'], $this->http->lastQuery());
    }

    public function testRemoveSendsDomain(): void
    {
        $client = $this->client(self::empty());
        $client->domains()->remove('example-test.com');

        self::assertSame('/domains/remove', $this->http->lastPath());
        self::assertSame(['domain' => 'example-test.com'], $this->http->lastQuery());
    }
}
