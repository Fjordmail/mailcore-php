<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Inboxcom\Mailcore\Http\Transport;
use Inboxcom\Mailcore\Resource\Datadump;
use Inboxcom\Mailcore\Resource\Domains;
use Inboxcom\Mailcore\Resource\Mailboxplans;
use Inboxcom\Mailcore\Resource\Mailfilter;
use Inboxcom\Mailcore\Resource\Reports;
use Inboxcom\Mailcore\Resource\Users;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Entry point for the Mailcore API.
 *
 *     $client = new MailcoreClient('your-api-key');
 *     $users  = $client->users()->list(filter: '*', limit: '0,100');
 *
 * By default it uses Guzzle (which is itself a PSR-18 client and ships PSR-17
 * factories). To run inside an environment that supplies its own HTTP stack —
 * a Roundcube plugin, a test harness — inject any PSR-18 client and PSR-17
 * request factory instead:
 *
 *     $client = new MailcoreClient('key', httpClient: $myPsr18Client, requestFactory: $myPsr17Factory);
 */
final class MailcoreClient
{
    /** Placeholder — set the real endpoint via the constructor, MAILCORE_BASE_URI, or config. */
    public const DEFAULT_BASE_URI = 'https://api.example.com';

    /** Default overall request timeout, in seconds (0 disables it). */
    public const DEFAULT_TIMEOUT = 30.0;

    /** Default connection timeout, in seconds (0 disables it). */
    public const DEFAULT_CONNECT_TIMEOUT = 10.0;

    private readonly Transport $transport;

    private ?Users $users = null;
    private ?Domains $domains = null;
    private ?Mailboxplans $mailboxplans = null;
    private ?Mailfilter $mailfilter = null;
    private ?Reports $reports = null;
    private ?Datadump $datadump = null;

    /**
     * @param float $timeout        Overall request timeout in seconds; 0 disables it.
     *                              Applied only to the default Guzzle client — ignored
     *                              when you inject your own $httpClient (configure it there).
     *                              A whole dump from datadump()->fetch() can be large, so
     *                              raise this (or pass 0) if you hit timeouts on it.
     * @param float $connectTimeout Connection timeout in seconds; 0 disables it. Same
     *                              "default client only" caveat as $timeout.
     */
    public function __construct(
        string $apiKey,
        string $baseUri = self::DEFAULT_BASE_URI,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('A Mailcore API key is required.');
        }

        $this->transport = new Transport(
            $httpClient ?? new GuzzleClient([
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
            ]),
            $requestFactory ?? new HttpFactory(),
            $apiKey,
            $baseUri,
        );
    }

    public function users(): Users
    {
        return $this->users ??= new Users($this->transport);
    }

    public function domains(): Domains
    {
        return $this->domains ??= new Domains($this->transport);
    }

    public function mailboxplans(): Mailboxplans
    {
        return $this->mailboxplans ??= new Mailboxplans($this->transport);
    }

    public function mailfilter(): Mailfilter
    {
        return $this->mailfilter ??= new Mailfilter($this->transport);
    }

    public function reports(): Reports
    {
        return $this->reports ??= new Reports($this->transport);
    }

    public function datadump(): Datadump
    {
        return $this->datadump ??= new Datadump($this->transport);
    }
}
