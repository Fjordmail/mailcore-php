<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Fixture;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** A PSR-18 client that always fails the way a network error would. */
final class ThrowingHttpClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new class ('Connection refused') extends \RuntimeException implements ClientExceptionInterface {};
    }
}
