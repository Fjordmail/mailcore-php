<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Inboxcom\Mailcore\Exception\TransportException;
use Inboxcom\Mailcore\MailcoreClient;
use Inboxcom\Mailcore\Tests\Fixture\ThrowingHttpClient;

/**
 * Exercises the shared HTTP behaviour through a representative resource call.
 */
final class TransportTest extends MailcoreTestCase
{
    public function testApiKeyIsTheFirstPathSegment(): void
    {
        $client = $this->client(self::json([]));
        $client->users()->list();

        self::assertStringStartsWith('https://api.example.com/SECRET-KEY/users/list', $this->http->lastUri());
        self::assertSame('/users/list', $this->http->lastPath());
    }

    public function testNullQueryParametersAreOmittedAndOthersEncoded(): void
    {
        $client = $this->client(self::json([]));
        $client->users()->list(filter: 'a b', limit: null, mailboxplanId: 4);

        $query = $this->http->lastQuery();
        self::assertSame(['filter' => 'a b', 'mailboxplan_id' => '4'], $query);
        self::assertStringContainsString('filter=a+b', $this->http->lastUri());
        self::assertArrayNotHasKey('limit', $query);
    }

    public function testEmptyBodyDecodesToNull(): void
    {
        $client = $this->client(self::empty(200));

        // count() casts the decoded value; an empty body must not blow up.
        self::assertSame(0, $client->users()->count(domain: 'example.com'));
    }

    public function testRawBodyIsReturnedUndecoded(): void
    {
        $binary = "\x1f\x8b\x08\x00rawPGPbytes";
        $client = $this->client(new Response(200, [], $binary));

        self::assertSame($binary, $client->datadump()->fetchLatest());
    }

    public function testInvalidJsonBecomesTransportException(): void
    {
        $client = $this->client(new Response(200, [], '{not json'));

        $this->expectException(TransportException::class);
        $client->users()->list();
    }

    public function testNetworkFailureBecomesTransportExceptionWithoutLeakingApiKey(): void
    {
        $client = new MailcoreClient('SUPER-SECRET', MailcoreClient::DEFAULT_BASE_URI, new ThrowingHttpClient(), new HttpFactory());

        try {
            $client->users()->list();
            self::fail('Expected TransportException');
        } catch (TransportException $e) {
            self::assertStringContainsString('/users/list', $e->getMessage());
            self::assertStringNotContainsString('SUPER-SECRET', $e->getMessage());
        }
    }

    public function testApiErrorMessageDoesNotLeakApiKey(): void
    {
        $client = $this->client(self::error(404, 'User not found'));

        try {
            $client->users()->get('x.demo.test@example.com');
            self::fail('Expected an exception');
        } catch (\Inboxcom\Mailcore\Exception\ApiException $e) {
            self::assertStringNotContainsString('SECRET-KEY', $e->getMessage());
            self::assertStringNotContainsString('SECRET-KEY', (string) $e);
        }
    }
}
