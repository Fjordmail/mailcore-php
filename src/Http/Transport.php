<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Http;

use Inboxcom\Mailcore\Exception\ApiException;
use Inboxcom\Mailcore\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Internal HTTP layer. Every Mailcore endpoint is a GET whose secret API key
 * is the first path segment (https://api.example.com/{apiKey}/...), so this
 * class is the single place that knows how to assemble that URL and the single
 * place responsible for keeping the key out of exceptions and logs.
 *
 * @internal Resource classes use this; it is not part of the public API.
 */
final class Transport
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly string $apiKey,
        private readonly string $baseUri,
    ) {
    }

    /**
     * Perform a request and decode the JSON body.
     *
     * @param array<string, scalar|null> $query Null values are dropped.
     *
     * @return mixed Decoded JSON (array/scalar), or null for an empty body.
     */
    public function get(string $path, array $query = []): mixed
    {
        $response = $this->send($path, $query);

        return $this->decode((string) $response->getBody(), $path);
    }

    /**
     * Perform a request and return the raw, undecoded body.
     *
     * Used by binary endpoints such as /datadump/fetch_latest.
     *
     * @param array<string, scalar|null> $query Null values are dropped.
     */
    public function getRaw(string $path, array $query = []): string
    {
        return (string) $this->send($path, $query)->getBody();
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function send(string $path, array $query): ResponseInterface
    {
        $request = $this->requestFactory
            ->createRequest('GET', $this->buildUrl($path, $query))
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Deliberately omit the URL: it contains the API key.
            throw new TransportException(
                sprintf('HTTP transport error while requesting %s', $path),
                previous: $e,
            );
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = (string) $response->getBody();
            throw ApiException::fromResponse(
                $status,
                $this->extractStatusMsg($body),
                $path,
                $body,
            );
        }

        return $response;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $url = rtrim($this->baseUri, '/')
            . '/' . rawurlencode($this->apiKey)
            . '/' . ltrim($path, '/');

        $params = array_filter($query, static fn (mixed $v): bool => $v !== null);
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    private function decode(string $body, string $path): mixed
    {
        $trimmed = trim($body);

        // Mailcore returns a bare, non-JSON `EMPTY` token (HTTP 200) for an empty
        // collection — e.g. /users/listsnapshots on a mailbox with no snapshots.
        // Treat it like a blank body so list callers see [] via (array) null.
        if ($trimmed === '' || $trimmed === 'EMPTY') {
            return null;
        }

        try {
            return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransportException(
                sprintf('Failed to decode JSON response from %s', $path),
                previous: $e,
            );
        }
    }

    private function extractStatusMsg(string $body): ?string
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) && isset($decoded['statusmsg']) && is_string($decoded['statusmsg'])
            ? $decoded['statusmsg']
            : null;
    }
}
