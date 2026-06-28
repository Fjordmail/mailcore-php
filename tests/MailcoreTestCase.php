<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Inboxcom\Mailcore\MailcoreClient;
use Inboxcom\Mailcore\Tests\Fixture\MockHttpClient;
use PHPUnit\Framework\TestCase;

abstract class MailcoreTestCase extends TestCase
{
    protected MockHttpClient $http;

    /** Build a client backed by a recording mock that replays the given responses. */
    protected function client(Response ...$responses): MailcoreClient
    {
        if ($responses === []) {
            $responses = [new Response(200, [], 'null')];
        }
        $this->http = new MockHttpClient(...$responses);

        return new MailcoreClient('SECRET-KEY', MailcoreClient::DEFAULT_BASE_URI, $this->http, new HttpFactory());
    }

    protected static function json(mixed $value): Response
    {
        return new Response(200, [], (string) json_encode($value));
    }

    protected static function error(int $status, string $statusmsg): Response
    {
        return new Response($status, [], (string) json_encode(['statusmsg' => $statusmsg]));
    }

    /** A successful response with an empty body, as the action endpoints return. */
    protected static function empty(int $status = 200): Response
    {
        return new Response($status, [], '');
    }

    /** A response with a raw (possibly non-JSON) body, e.g. Mailcore's bare `EMPTY` token. */
    protected static function raw(string $body, int $status = 200): Response
    {
        return new Response($status, [], $body);
    }
}
