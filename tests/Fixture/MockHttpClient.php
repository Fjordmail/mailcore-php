<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Fixture;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A canned PSR-18 client for tests. Records every request and replays a queue
 * of responses; once the queue is down to its last entry, that entry is
 * returned for any further calls.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];
    }

    public function lastRequest(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }

    public function lastUri(): string
    {
        return (string) $this->lastRequest()->getUri();
    }

    /** Path of the last request, excluding the leading /{apiKey} segment. */
    public function lastPath(): string
    {
        $path = (string) parse_url($this->lastUri(), PHP_URL_PATH);

        return (string) preg_replace('#^/[^/]+#', '', $path);
    }

    /** @return array<string, string> Decoded query parameters of the last request. */
    public function lastQuery(): array
    {
        parse_str((string) parse_url($this->lastUri(), PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    public function callCount(): int
    {
        return count($this->requests);
    }
}
